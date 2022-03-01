!function(){var e={703:function(e,t){var n;!function(){"use strict";var r={}.hasOwnProperty;function o(){for(var e=[],t=0;t<arguments.length;t++){var n=arguments[t];if(n){var a=typeof n;if("string"===a||"number"===a)e.push(n);else if(Array.isArray(n)){if(n.length){var i=o.apply(null,n);i&&e.push(i)}}else if("object"===a)if(n.toString===Object.prototype.toString)for(var c in n)r.call(n,c)&&n[c]&&e.push(c);else e.push(n.toString())}}return e.join(" ")}e.exports?(o.default=o,e.exports=o):void 0===(n=function(){return o}.apply(t,[]))||(e.exports=n)}()}},t={};function n(r){var o=t[r];if(void 0!==o)return o.exports;var a=t[r]={exports:{}};return e[r](a,a.exports,n),a.exports}n.n=function(e){var t=e&&e.__esModule?function(){return e.default}:function(){return e};return n.d(t,{a:t}),t},n.d=function(e,t){for(var r in t)n.o(t,r)&&!n.o(e,r)&&Object.defineProperty(e,r,{enumerable:!0,get:t[r]})},n.o=function(e,t){return Object.prototype.hasOwnProperty.call(e,t)},function(){"use strict";var e=["colorScheme","contentMaxWidth","children"];function t(){return t=Object.assign||function(e){for(var t=1;t<arguments.length;t++){var n=arguments[t];for(var r in n)Object.prototype.hasOwnProperty.call(n,r)&&(e[r]=n[r])}return e},t.apply(this,arguments)}var r=function(n){n.colorScheme,n.contentMaxWidth;var r=n.children,o=function(e,t){if(null==e)return{};var n,r,o=function(e,t){if(null==e)return{};var n,r,o={},a=Object.keys(e);for(r=0;r<a.length;r++)n=a[r],t.indexOf(n)>=0||(o[n]=e[n]);return o}(e,t);if(Object.getOwnPropertySymbols){var a=Object.getOwnPropertySymbols(e);for(r=0;r<a.length;r++)n=a[r],t.indexOf(n)>=0||Object.prototype.propertyIsEnumerable.call(e,n)&&(o[n]=e[n])}return o}(n,e);return React.createElement("div",t({className:"fc-cond-section"},o),React.createElement("div",{className:"fc-cond-blocks"},r))},o=n(703),a=n.n(o),i=window.wp.element,c=window.wp.components,l=window.wp.blockEditor,s=window.wp.blocks,u=(wp.element.createElement,{});u.fluentcrm=React.createElement("svg",{width:"100%",height:"100%",viewBox:"0 0 300 300",version:"1.1",xmlns:"http://www.w3.org/2000/svg"},React.createElement("path",{fill:"#7743e6",d:"M300,30c0,-16.557 -13.443,-30 -30,-30l-240,0c-16.557,0 -30,13.443 -30,30l0,240c0,16.557 13.443,30 30,30l240,0c16.557,0 30,-13.443 30,-30l0,-240Z"}),React.createElement("g",null,React.createElement("path",{d:"M250.955,71.122c0,-0 -129.408,34.674 -181.023,48.505c-12.32,3.301 -20.887,14.465 -20.887,27.22c-0,9.696 -0,18.989 -0,18.989c-0,0 103.954,-27.854 162.681,-43.59c23.139,-6.2 39.229,-27.169 39.229,-51.124c0,-0 0,-0 0,-0Z",fill:"white"}),React.createElement("path",{d:"M173.46,154.928c-0,0 -68.092,18.246 -103.528,27.741c-12.32,3.301 -20.887,14.465 -20.887,27.22c-0,9.696 -0,18.989 -0,18.989c-0,0 48.721,-13.054 85.185,-22.825c23.14,-6.2 39.23,-27.169 39.23,-51.124c-0,-0.001 -0,-0.001 -0,-0.001Z",fill:"white"})));var f=u;function d(e,t,n,r,o,a,i){try{var c=e[a](i),l=c.value}catch(e){return void n(e)}c.done?t(l):Promise.resolve(l).then(r,o)}function p(e){return function(){var t=this,n=arguments;return new Promise((function(r,o){var a=e.apply(t,n);function i(e){d(a,r,o,i,c,"next",e)}function c(e){d(a,r,o,i,c,"throw",e)}i(void 0)}))}}function h(e,t){(null==t||t>e.length)&&(t=e.length);for(var n=0,r=new Array(t);n<t;n++)r[n]=e[n];return r}var m=wp.i18n,__=m.__,_x=m._x;(0,s.registerBlockType)("fluent-crm/conditional-content",{title:__("Conditional Section"),description:__("Add a conditional section that separates content, and put any other block into it. Show/hide this section based on visitors login state or available tags"),category:"layout",icon:f.fluentcrm,keywords:[_x("conditional"),_x("section")],supports:{align:["wide","full"],anchor:!0},attributes:{condition_type:{type:"string",default:"show_if_tag_exist"},tag_ids:{type:"array",default:[]}},edit:function(e){var t,n,o=e.attributes,a=e.setAttributes,s=o.condition_type,u=o.tag_ids,f=(t=(0,i.useState)([]),n=2,function(e){if(Array.isArray(e))return e}(t)||function(e,t){var n=null==e?null:"undefined"!=typeof Symbol&&e[Symbol.iterator]||e["@@iterator"];if(null!=n){var r,o,a=[],_n=!0,i=!1;try{for(n=n.call(e);!(_n=(r=n.next()).done)&&(a.push(r.value),!t||a.length!==t);_n=!0);}catch(e){i=!0,o=e}finally{try{_n||null==n.return||n.return()}finally{if(i)throw o}}return a}}(t,n)||function(e,t){if(e){if("string"==typeof e)return h(e,t);var n=Object.prototype.toString.call(e).slice(8,-1);return"Object"===n&&e.constructor&&(n=e.constructor.name),"Map"===n||"Set"===n?Array.from(e):"Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?h(e,t):void 0}}(t,n)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()),d=f[0],m=f[1];return(0,i.useEffect)((function(){function e(){return(e=p(regeneratorRuntime.mark((function e(){return regeneratorRuntime.wrap((function(e){for(;;)switch(e.prev=e.next){case 0:window._fcrm_available_tags?m(window._fcrm_available_tags):wp.apiFetch({path:"fluent-crm/v2/reports/options?fields=tags"}).then((function(e){window._fcrm_available_tags=e.options.tags,m(e.options.tags)}));case 1:case"end":return e.stop()}}),e)})))).apply(this,arguments)}!function(){e.apply(this,arguments)}()}),[]),React.createElement(i.Fragment,null,React.createElement(l.InspectorControls,null,React.createElement(c.PanelBody,{title:__("Conditional Settings")},React.createElement(c.SelectControl,{label:__("Condition Type"),value:s,onChange:function(e){a({condition_type:e||"show_if_tag_exist"})},options:[{value:"show_if_tag_exist",label:"Show IF in selected tag"},{value:"show_if_tag_not_exist",label:"Show IF not in selected tag"},{value:"show_if_logged_in",label:"Show if user is logged in"},{value:"show_if_public_users",label:"Show if user is not logged in"}]}),"show_if_tag_exist"!=s&&"show_if_tag_not_exist"!=s&&s?"":React.createElement("div",{className:"fcrm-gb-multi-checkbox"},React.createElement("h4",null,"Select Targeted Tags"),React.createElement("ul",null,d.map((function(e){return React.createElement(c.ToggleControl,{value:e.id,label:e.title,checked:-1!=u.indexOf(e.id),onChange:function(t){var n,r,i;n=t,r=e.id,i=jQuery.extend(!0,[],o.tag_ids),n?-1==i.indexOf(r)&&(i.push(r),a({tag_ids:i})):(i.splice(i.indexOf(r),1),a({tag_ids:i}))}})})))),React.createElement("div",{className:"fc_cd_info"},React.createElement("hr",null),React.createElement("b",null,"Tips:"),React.createElement("ul",null,React.createElement("li",null,"This will show/hide only if any of the selected tags is matched."),React.createElement("li",{style:{backgroundColor:"#ffffd7"}},"The yellow background in the content is only for editor and to identify the conditional contents"))))),React.createElement(r,{colorScheme:s,contentMaxWidth:u},React.createElement(l.InnerBlocks,null)))},save:function(e){var t=e.attributes,n=t.colorScheme,o=t.contentMaxWidth,i=t.attachmentId;return React.createElement(r,{colorScheme:n,contentMaxWidth:o,className:a()(i&&"has-background-image-".concat(i))},React.createElement(l.InnerBlocks.Content,null))}})}()}();