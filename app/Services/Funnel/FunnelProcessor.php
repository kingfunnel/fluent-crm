<?php

namespace FluentCrm\App\Services\Funnel;

use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Models\FunnelSequence;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Models\Subscriber;

class FunnelProcessor
{
    private $subscribersCache = [];

    private $sequenceFunnelCache = [];

    private $funnelCache = [];

    public function getSubscriber($id)
    {
        if (isset($this->subscribersCache[$id])) {
            return $this->subscribersCache[$id];
        }
        $subscriber = Subscriber::find($id);
        $this->subscribersCache[$id] = $subscriber;
        return $this->subscribersCache[$id];
    }

    public function getSequence($id)
    {
        if (isset($this->sequenceFunnelCache[$id])) {
            return $this->sequenceFunnelCache[$id];
        }
        $funnelAction = FunnelSequence::find($id);
        $this->sequenceFunnelCache[$id] = $funnelAction;
        return $this->sequenceFunnelCache[$id];
    }

    public function getFunnel($id)
    {
        if (isset($this->funnelCache[$id])) {
            return $this->funnelCache[$id];
        }
        $funnel = Funnel::find($id);
        $this->funnelCache[$id] = $funnel;
        return $this->funnelCache[$id];
    }

    public function startFunnelSequence($funnel, $subscriberData, $funnelSubArgs = [], $subscriber = false)
    {
        if (!$subscriber) {
            // it's new so let's create new subscriber
            $subscriber = FunnelHelper::createOrUpdateContact($subscriberData);
            if ($subscriber->status == 'pending' || $subscriber->status == 'unsubscribed') {
                $subscriber->sendDoubleOptinEmail();
            }
        }

        $args = [
            'status' => ($subscriber->status == 'pending' || $subscriber->status == 'unsubscribed') ? 'pending' : 'draft'
        ];

        if ($funnelSubArgs) {
            $args = wp_parse_args($args, $funnelSubArgs);
        }

        (new FunnelProcessor())->startSequences($subscriber, $funnel, $args);
    }

    public function startSequences($subscriber, $funnel, $funnelSubArgs = [])
    {
        $sequencePoints = (new SequencePoints($funnel));

        if (!$sequencePoints->hasSequences()) {
            return;
        }

        $data = [
            'funnel_id'     => $funnel->id,
            'subscriber_id' => $subscriber->id,
            'status'        => 'draft'
        ];

        if ($funnelSubArgs) {
            $data = wp_parse_args($funnelSubArgs, $data);
        }

        if($subscriber->status == 'subscribed') {
            $data['status'] = 'active';
        }

        // let's create an empty sequence_subscriber
        $funnelSubscriber = FunnelSubscriber::create($data);

        if ($funnelSubscriber->status != 'pending') {
            $this->processSequencePoints($sequencePoints, $subscriber, $funnelSubscriber);
        }
    }

    public function processSequencePoints(SequencePoints $sequencePoints, $subscriber, $funnelSubscriber)
    {
        if (!$sequencePoints->hasSequences()) {
            $this->completeFunnelSequence($funnelSubscriber);
            return;
        }

        foreach ($sequencePoints->getCurrentSequences() as $sequence) {
            $this->processSequence($subscriber, $sequence, $funnelSubscriber->id);
            if($sequence->action_name == 'end_this_funnel') {
                return;
            }
        }

        $nextSequence = $sequencePoints->getNextSequence();
        $requiredBenchMark = $sequencePoints->getRequiredBenchmark();

        if ($nextSequence && $requiredBenchMark) {
            if ($nextSequence->sequence < $requiredBenchMark->sequence) {
                $requiredBenchMark = false;
            }
        }

        if ($requiredBenchMark) {
            // if we have required benchmark then wait for that
            FunnelSubscriber::where('id', $funnelSubscriber->id)
                ->update([
                    'next_sequence'       => $requiredBenchMark->sequence,
                    'next_sequence_id'    => $requiredBenchMark->id,
                    'next_execution_time' => null,
                    'status'              => 'waiting'
                ]);
            return;
        }

        if (!$sequencePoints->hasNext()) {
            $this->completeFunnelSequence($funnelSubscriber);
            return;
        }

        if ($nextSequence) {
            FunnelSubscriber::where('id', $funnelSubscriber->id)
                ->update([
                    'next_sequence'       => $nextSequence->sequence,
                    'next_sequence_id'    => $nextSequence->id,
                    'next_execution_time' => date('Y-m-d H:i:s', strtotime(current_time('mysql')) + $nextSequence->delay),
                    'status'              => 'active'
                ]);
        }
    }

    /**
     * @param $sequences
     * @param $subscriber
     * @param $funnelSubscriber
     * @deprecated 1.2.0 Use processSequencePoints method
     */
    public function processSequences($sequences, $subscriber, $funnelSubscriber)
    {
        _deprecated_function(__METHOD__, '1.2.0', "(new FunnelProcessor)->processSequencePoints()");
        error_log('This method should not be called');

        $funnel = $this->getFunnel($funnelSubscriber->funnel_id);
        $funnelPoints = new SequencePoints($funnel, $funnelSubscriber);
        $this->processSequencePoints($funnelPoints, $subscriber, $funnelSubscriber);
    }

    public function processSequence($subscriber, $sequence, $funnelSubscriberId)
    {
        $funnelMetric = $this->recordFunnelMetric($subscriber, $sequence);
        FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'complete');
        do_action('fluentcrm_funnel_sequence_handle_' . $sequence->action_name, $subscriber, $sequence, $funnelSubscriberId, $funnelMetric);
    }

    public function completeFunnelSequence($funnelSubscriber)
    {
        FunnelSubscriber::where('id', $funnelSubscriber->id)
            ->update([
                'status' => 'completed'
            ]);
    }

    public function followUpSequenceActions()
    {
        $jobs = FunnelSubscriber::where('status', 'active')
            ->whereHas('funnel', function ($q) {
                $q->where('status', 'published');
            })
            ->where('next_execution_time', '<=', current_time('mysql'))
            ->whereNotNull('next_execution_time')
            ->limit(200)// we want to process 200 records each time
            ->get();

        foreach ($jobs as $job) {
            $this->processFunnelAction($job);
        }
    }

    public function processFunnelAction($funnelSubscriber)
    {
        $subscriber = $this->getSubscriber($funnelSubscriber->subscriber_id);
        $funnel = $this->getFunnel($funnelSubscriber->funnel_id);

        if (!$subscriber) {
            FunnelSubscriber::where('id', $funnelSubscriber->id)->update([
                'status' => 'skipped'
            ]);
            return false;
        }

        $sequencePoints = new SequencePoints($funnel, $funnelSubscriber);

        $this->processSequencePoints($sequencePoints, $subscriber, $funnelSubscriber);
    }

    public function startFunnelFromSequencePoint($startSequence, $subscriber, $args = [], $metricArgs = [])
    {
        $funnelSubscriber = FunnelHelper::ifAlreadyInFunnel($startSequence->funnel_id, $subscriber->id);

        $this->recordFunnelMetric($subscriber, $startSequence, $metricArgs);

        if (!$funnelSubscriber) {
            // we have to create a funnel subscriber
            $data = [
                'funnel_id'            => $startSequence->funnel_id,
                'subscriber_id'        => $subscriber->id,
                'status'               => ($subscriber->status == 'subscribed') ? 'active' : 'pending',
                'starting_sequence_id' => $startSequence->id,
                'last_sequence_status' => 'completed',
                'next_sequence'        => $startSequence->sequence + 1,
                'last_sequence_id'     => $startSequence->id,
                'last_executed_time'   => current_time('mysql'),
                'source_trigger_name'  => $startSequence->action_name
            ];

            if ($args) {
                $data = wp_parse_args($args, $data);
            }
            // let's create an empty sequence_subscriber
            $funnelSubscriber = FunnelSubscriber::create($data);
        } else {
            // We already have funnel subscriber. Now we have to update that
            $lastSequence = $funnelSubscriber->last_sequence;

            if (!$lastSequence || ($lastSequence->sequence <= $startSequence->sequence)) {
                $nextSequence = FunnelSequence::where('sequence', '>', $startSequence->sequence)
                    ->orderBy('sequence', 'ASC')
                    ->first();

                if (!$nextSequence) {
                    $this->completeFunnelSequence($funnelSubscriber);
                    return;
                }

                $funnelSubscriber->last_sequence_id = $startSequence->id;
                $funnelSubscriber->next_sequence_id = $nextSequence->id;
                $funnelSubscriber->next_sequence = $nextSequence->sequence;
                if ($funnelSubscriber->status == 'waiting') {
                    $funnelSubscriber->status = 'active';
                }
                $funnelSubscriber->next_execution_time = current_time('mysql');  // we are auto advancing the funnel
                $funnelSubscriber->save();
            } else {
                // this already advanced than our target
                // We have to check if we have to fire immediately
                if ($funnelSubscriber->next_sequence - 1 == $startSequence->sequence) {
                    // we are just make the time with current time if that had a timer for the target sequence
                    $funnelSubscriber->next_execution_time = current_time('mysql');
                    $funnelSubscriber->save();
                } else {
                    return; // It will work as it is; This funnel don't need any help
                }
            }
        }

        if ($funnelSubscriber->status == 'pending') {
            return; // We need double-optin from this user.
        }

        $funnel = $this->getFunnel($startSequence->funnel_id);

        $sequencePoints = new SequencePoints($funnel, $funnelSubscriber);

        $this->processSequencePoints($sequencePoints, $subscriber, $funnelSubscriber);
    }

    public function recordFunnelMetric($subscriber, $sequence, $metricArgs = [])
    {
        $data = [
            'funnel_id'     => $sequence->funnel_id,
            'sequence_id'   => $sequence->id,
            'subscriber_id' => $subscriber->id
        ];

        if ($metricArgs) {
            $data = wp_parse_args($data, $metricArgs);
        }

        return FunnelMetric::updateOrCreate($data, [
            'funnel_id'     => $sequence->funnel_id,
            'sequence_id'   => $sequence->id,
            'subscriber_id' => $subscriber->id
        ]);
    }

    public function initChildSequences($parent, $isMatched, $subscriber, $funnelSubscriberId, $funnelMetric)
    {
        $conditionType = 'no';
        if($isMatched) {
            $conditionType = 'yes';
        }
        // find the corresponding sequence
        $sequences = FunnelSequence::where('funnel_id', $parent->funnel_id)
            ->where('parent_id', $parent->id)
            ->where('condition_type', $conditionType)
            ->get();

        $immediateSequences = [];
        $firstSequence = $sequences[0];
        $nextSequence = false;

        foreach ($sequences as $sequence) {
            if ($sequence->c_delay == $firstSequence->c_delay) {
                $immediateSequences[] = $sequence;
            } else {
                if (!$nextSequence) {
                    $nextSequence = $sequence;
                }
                if ($sequence->c_delay < $nextSequence->c_delay) {
                    $nextSequence = $sequence;
                }
            }
        }


        $waitTimes = 0;
        foreach ($immediateSequences as $immediateSequence) {
           $this->processSequence($subscriber, $immediateSequence, $funnelSubscriberId);
           if($immediateSequence->action_name == 'end_this_funnel') {
               return ;
           }

           if($immediateSequence->action_name == 'fluentcrm_wait_times') {
               $waitTimes = FunnelHelper::getDelayInSecond($immediateSequence);
           }
        }

        if($nextSequence) {
            return FunnelSubscriber::where('id', $funnelSubscriberId)
                ->update([
                    'next_sequence'       => $nextSequence->sequence,
                    'next_sequence_id'    => $nextSequence->id,
                    'next_execution_time' => date('Y-m-d H:i:s', strtotime(current_time('mysql')) + $nextSequence->delay),
                    'status'              => 'active'
                ]);
        }

        $funnel = $this->getFunnel($parent->funnel_id);
        $funnelSubscriber = FunnelSubscriber::where('id', $funnelSubscriberId)->first();

        $funnelSubscriber->last_sequence_id = $parent->id;
        // we don't have next sequence so we have to loop back to the parent
        $sequencePoints = new SequencePoints($funnel, $funnelSubscriber);

        if($waitTimes && $currentNextSequences = $sequencePoints->getCurrentSequences()) {
            $nextSequence = $currentNextSequences[0];
            return FunnelSubscriber::where('id', $funnelSubscriberId)
                ->update([
                    'next_sequence'       => $nextSequence->sequence,
                    'next_sequence_id'    => $nextSequence->id,
                    'next_execution_time' => date('Y-m-d H:i:s', strtotime(current_time('mysql')) + $waitTimes),
                    'status'              => 'active'
                ]);
        }

        $this->processSequencePoints($sequencePoints, $subscriber, $funnelSubscriber);
    }
}
