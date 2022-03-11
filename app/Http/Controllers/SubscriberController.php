<?php

namespace FluentCrm\App\Http\Controllers;

use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\CustomEmailCampaign;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberNote;
use FluentCrm\App\Models\SubscriberPivot;
use FluentCrm\App\Services\Helper;
use FluentCrm\Includes\Helpers\Arr;
use FluentCrm\Includes\Request\Request;

class SubscriberController extends Controller
{
    public function index()
    {
        $subscribers = Subscriber::with('tags', 'lists')
            ->when($this->request->has('tags'), function ($query) {
                $query->filterByTags($this->request->get('tags'));
            })
            ->when($this->request->has('statuses'), function ($query) {
                $query->filterByStatues($this->request->get('statuses'));
            })
            ->when($this->request->has('lists'), function ($query) {
                $query->filterByLists($this->request->get('lists'));
            })
            ->when($this->request->has('search'), function ($query) {
                $query->searchBy($this->request->get('search'));
            })
            ->when($this->request->has('sort_by'), function ($query) {
                $query->orderBy($this->request->get('sort_by'), $this->request->get('sort_type'));
            });

        $subscribers = $subscribers->paginate();

        if($this->request->get('custom_fields') == 'true') {
            // we have to include custom fields
            foreach ($subscribers as $subscriber) {
                $subscriber->custom_fields = $subscriber->custom_fields();
            }
        }

        return $this->sendSuccess([
            'subscribers' => $subscribers,
            'custom' => $this->request->get('custom_fields')
        ]);
    }

    /**
     * Find a subscriber by id
     *
     * @return \WP_REST_Response $object
     */
    public function show()
    {
        $subscriber = Subscriber::with('tags', 'lists')->find($this->request->get('id'));

        if ($subscriber->user_id) {
            $subscriber->user_edit_url = get_edit_user_link($subscriber->user_id);
        }

        $with = $this->request->get('with', []);

        if (in_array('stats', $with)) {
            $subscriber->stats = $subscriber->stats();
        }

        if (in_array('subscriber.custom_values', $with)) {
            $subscriber->custom_values = (object)$subscriber->custom_fields();
        }

        if ($subscriber->date_of_birth == '0000-00-00') {
            $subscriber->date_of_birth = '';
        }

        if ($subscriber->status == 'unsubscribed') {
            $subscriber->unsubscribe_reason = $subscriber->unsubscribeReason();
        } else if ($subscriber->status == 'bounced' || $subscriber->status == 'complained') {
            $subscriber->unsubscribe_reason = $subscriber->unsubscribeReason('reason');
        }

        $data = [
            'subscriber' => $subscriber
        ];

        if (in_array('custom_fields', $with)) {
            $data['custom_fields'] = fluentcrm_get_option('contact_custom_fields', []);
        }

        return $this->sendSuccess($data);
    }

    public function updateProperty()
    {
        $column = sanitize_text_field($this->request->get('property'));
        $value = sanitize_text_field($this->request->get('value'));
        $subscriberIds = $this->request->get('subscribers');

        $validColumns = ['status', 'contact_type', 'avatar'];
        $subscriberStatuses = fluentcrm_subscriber_statuses();
        $leadStatuses = fluentcrm_contact_types();

        $this->validate([
            'column'         => $column,
            'value'          => $value,
            'subscriber_ids' => $subscriberIds
        ], [
            'column'         => 'required',
            'value'          => 'required',
            'subscriber_ids' => 'required'
        ]);

        if (!in_array($column, $validColumns)) {
            return $this->sendError([
                'message' => 'Column is not valid'
            ]);
        }

        if ($column == 'status' && !in_array($value, $subscriberStatuses)) {
            return $this->sendError([
                'message' => 'Value is not valid'
            ]);
        } else if ($column == 'contact_type' && !isset($leadStatuses[$value])) {
            return $this->sendError([
                'message' => 'Value is not valid'
            ]);
        }

        $subscribers = Subscriber::whereIn('id', $subscriberIds)->get();

        foreach ($subscribers as $subscriber) {
            $oldValue = $subscriber->{$column};
            if ($oldValue != $value) {
                $subscriber->{$column} = $value;
                $subscriber->save();
                if (in_array($column, ['status', 'contact_type'])) {
                    do_action('fluentcrm_subscriber_' . $column . '_to_' . $value, $subscriber, $oldValue);
                }
            }
        }

        Subscriber::whereIn('id', $subscriberIds)->update([$column => $value]);

        return $this->sendSuccess([
            'message' => 'Subscribers successfully updated'
        ]);
    }

    public function deleteSubscribers(Request $request)
    {
        $subscriberIds = $request->get('subscribers');

        $this->validate(
            ['subscriber_ids' => $subscriberIds],
            ['subscriber_ids' => 'required']
        );

        Helper::deleteContacts($subscriberIds);

        return $this->sendSuccess([
            'message' => __('Selected Subscribers has been deleted', 'fluent-crm')
        ]);
    }

    /**
     * Tag a subscriber with Tags or Lists.
     */
    public function tagger()
    {
        $model = $this->resolveModel();

        $subscribers = $this->request->get('subscribers');
        $attachments = $this->attachments($model, 'attach');
        $detachments = $this->attachments($model, 'detach');

        foreach ($subscribers as $subscriber) {
            SubscriberPivot::attach($attachments, $subscriber, $model);
            SubscriberPivot::detach($detachments, $subscriber, $model);
        }

        return $this->sendSuccess([
            'message'     => 'Successfully updated the ' . _n('subscriber', 'subscribers', count($subscribers)) . '.',
            'subscribers' => Subscriber::with('tags', 'lists')->whereIn('id', $subscribers)->get()
        ]);
    }

    /**
     * Store a subscriber.
     *
     * @return array
     */
    public function store(Request $request)
    {
        $forceUpdate = $request->get('__force_update') == 'yes';

        if (!$forceUpdate) {
            $data = $this->validate($request->all(), [
                'email'  => 'required|email|unique:fc_subscribers',
                'status' => 'required'
            ], [
                'email.unique' => __('Provided email already assigned to another subscriber.', 'fluent-crm')
            ]);
        } else {
            $data = $this->validate($request->all(), [
                'email'  => 'required|email',
                'status' => 'required'
            ], [
                'email.unique' => __('Provided email already assigned to another subscriber.', 'fluent-crm')
            ]);
        }

        unset($data['__force_update']);

        if ($this->isNew()) {
            $contact = Subscriber::store($data);

            do_action('fluentcrm_contact_created', $contact, $data);

            return [
                'message' => __('Successfully added the subscriber.', 'fluent-crm'),
                'contact' => $contact,
                'action_type' => 'created'
            ];
        } else if ($forceUpdate) {
            $contact = FluentCrmApi('contacts')->createOrUpdate($data, false, false);
            if ($contact->status == 'pending') {
                $contact->sendDoubleOptinEmail();
            }

            return [
                'message' => __('contact has been successfully updated.', 'fluent-crm'),
                'contact' => $contact,
                'action_type' => 'updated'
            ];
        }

        return $this->sendError([
            'message' => 'Sorry contact already exist'
        ], 423);
    }

    public function updateSubscriber(Request $request, $id)
    {
        $subscriber = Subscriber::findOrFail($id);
        $originalData = $request->getJson('subscriber');

        if(isset($originalData['email'])) {
            $data = $this->validate($originalData, [
                'email' => 'required|email|unique:fc_subscribers,email,' . $id,
            ], [
                'email.unique' => __('Provided email already assigned to another subscriber.', 'fluent-crm')
            ]);
        }

        if(isset($data['email'])) {
            // Maybe update user id
            if ($user = get_user_by('email', $data['email'])) {
                $data['user_id'] = $user->ID;
            }
        }

        if (!empty($data['user_id'])) {
            $data['user_id'] = intval($data['user_id']);
        }

        if (isset($data['date_of_birth']) && empty($data['date_of_birth'])) {
            $data['date_of_birth'] = '0000-00-00';
        }

        $validFields = $subscriber->getFillable();

        $validData = [];

        foreach ($data as $key => $datum) {
            if(in_array($key, $validFields)) {
                if(is_string($datum)) {
                    $datum = sanitize_text_field($datum);
                }
                $validData[$key] = $datum;
            }
        }

        unset($validData['created_at']);
        unset($validData['last_activity']);
        $customValues = Arr::get($originalData, 'custom_values');

        if(!$customValues && !$validFields) {
            return $this->sendError([
                'message' => __('Provided data is not valid', 'fluent-crm')
            ]);
        }

        $oldEmail = $subscriber->email;

        $subscriber->fill($validData);
        $dirtyFields = $subscriber->getDirty();

        $subscriber->save();

        if ($customValues) {
            $subscriber->syncCustomFieldValues($customValues, true);
        }

        if ($dirtyFields) {

            if(isset($dirtyFields['email'])) {
                do_action('fluentcrm_contact_email_changed', $subscriber, $oldEmail);
            }

            do_action('fluentcrm_contact_updated', $subscriber, $validData);
        }

        return $this->sendSuccess([
            'message' => __('Subscriber successfully updated', 'fluent-crm'),
            'contact' => $subscriber,
            'isDirty' => !!$dirtyFields
        ], 200);
    }

    /**
     * Resolve the appropriate model e.g. Tag or, Lists
     *
     * @return string
     */
    private function resolveModel()
    {
        $type = $this->request->type;

        $model = 'FluentCrm\App\Models\\' . ($type === 'tags' ? 'Tag' : 'Lists');

        return $model;
    }

    /**
     * Get the attachment options e.g. attach or, detach
     *
     * @param \FluentCrm\App\Models\Model $model
     * @param string $type
     * @return array
     */
    private function attachments($model, $type = 'attach')
    {
        $attachments = $this->request->get($type, []);

        if ($attachments) {

            $attachments = $model::select('id')->whereIn('slug', $attachments)->get();

            if ($attachments) {
                $attachments = array_map(function ($item) {
                    return $item['id'];
                }, $attachments->toArray());
            }
        }

        return $attachments;
    }

    /**
     * Handles if subscriber already exist.
     *
     * @return bool
     */
    private function isNew()
    {
        $subscriber = Subscriber::where(
            'email', $this->request->get('email')
        )->first();

        if ($subscriber) {
            return false;
        }

        return true;
    }

    public function emails(Request $request, $subscriberId)
    {
        $emails = CampaignEmail::where('subscriber_id', $subscriberId)
            ->orderBy('id', 'DESC')
            ->paginate();

        return apply_filters('fluentcrm_contact_emails', [
            'emails' => $emails
        ], $subscriberId);
    }

    public function deleteEmails(Request $request, $subscriberId)
    {
        $emailIds = $request->get('email_ids');
        CampaignEmail::where('subscriber_id', $subscriberId)
            ->whereIn('id', $emailIds)
            ->delete();
        return [
            'message' => __('Selected emails has been deleted', 'fluent-crm')
        ];
    }

    public function addNote(Request $request, $id)
    {
        $note = $this->validate($request->get('note'), [
            'title'       => 'required',
            'description' => 'required',
            'type'        => 'required'
        ]);

        $subsciberNote = SubscriberNote::create(array_merge(
            wp_unslash($note), ['subscriber_id' => $id]
        ));

        return $this->sendSuccess([
            'note'    => $subsciberNote,
            'message' => __('Note successfully added', 'fluent-crm')
        ]);
    }

    public function updateNote(Request $request, $id, $noteId)
    {
        $note = $this->validate($request->get('note'), [
            'title'       => 'required',
            'description' => 'required',
            'type'        => 'required'
        ]);

        $note = Arr::only(wp_unslash($note), ['title', 'description', 'type']);
        $subsciberNote = SubscriberNote::find($noteId);
        $subsciberNote->fill($note);
        $subsciberNote->save();

        return $this->sendSuccess([
            'note'    => $subsciberNote,
            'message' => __('Note successfully updated', 'fluent-crm')
        ]);
    }

    public function deleteNote($id, $noteId)
    {
        SubscriberNote::where('id', $noteId)->delete();

        return $this->sendSuccess([
            'message' => __('Note successfully deleted', 'fluent-crm')
        ]);
    }

    public function getNotes()
    {
        $subscriberId = $this->request->get('id');

        $notes = SubscriberNote::where('subscriber_id', $subscriberId)
            ->with('added_by')
            ->orderBy('id', 'DESC')
            ->paginate();

        return $this->sendSuccess([
            'notes' => $notes
        ]);
    }

    public function getFormSubmissions()
    {
        $provider = $this->request->get('provider');
        $subscriberId = intval($this->request->get('id'));
        $subscriber = Subscriber::where('id', $subscriberId)->first();

        $data = $this->app->applyCustomFilters('get_form_submissions_' . $provider, [
            'data'  => [],
            'total' => 0
        ], $subscriber);

        return $this->sendSuccess([
            'submissions' => $data
        ]);
    }

    public function getSupportTickets()
    {
        $provider = $this->request->get('provider');
        $subscriberId = intval($this->request->get('id'));
        $subscriber = Subscriber::where('id', $subscriberId)->first();

        $data = $this->app->applyCustomFilters('get_support_tickets_' . $provider, [
            'data'  => [],
            'total' => 0
        ], $subscriber);

        return $this->sendSuccess([
            'tickets' => $data
        ]);
    }

    public function sendDoubleOptinEmail(Request $request, $id)
    {
        $subscriber = Subscriber::where('id', $id)->first();

        if ($subscriber == 'subscribed') {
            return $this->sendError([
                'message' => __('Contact Already Subscribed', 'fluent-crm')
            ]);
        }

        $subscriber->sendDoubleOptinEmail();

        return $this->sendSuccess([
            'message' => __('Double OptIn email has been sent', 'fluent-crm')
        ]);
    }

    public function getTemplateMock(Request $request, $id)
    {
        $emailMock = CustomEmailCampaign::getMock();
        $emailMock['title'] = __('Custom Email to Contact', 'fluent-crm');
        return [
            'email_mock' => $emailMock
        ];
    }

    public function sendCustomEmail(Request $request, $contactId)
    {
        $contact = Subscriber::findOrFail($contactId);
        if ($contact->status != 'subscribed') {
            return $this->sendError([
                'message' => __('Subscriber\'s status need to be subscribed.', 'fluent-crm')
            ]);
        }

        add_action('wp_mail_failed', function ($wpError) {
            return $this->sendError([
                'message' => $wpError->get_error_message()
            ]);
        }, 10, 1);

        $newCampaign = $request->get('campaign');
        unset($newCampaign['id']);

        $campaign = CustomEmailCampaign::create($newCampaign);

        $campaign->subscribe([$contactId], [
            'status'       => 'scheduled',
            'scheduled_at' => current_time('mysql')
        ]);

        do_action('fluentcrm_process_contact_jobs', $contact);

        return [
            'message' => __('Custom Email has been successfully sent', 'fluent-crm')
        ];
    }

    public function getExternalView(Request $request, $subscriberId)
    {
        $subscriber = Subscriber::findOrFail($subscriberId);
        $sectionId = $request->get('section_provider');

        return apply_filters('fluencrm_profile_section_'.$sectionId, [
            'heading' => '',
            'content_html' => ''
        ], $subscriber);

    }

}
