(()=>{var o={669:o=>{"use strict";o.exports=jQuery}},n={};function r(a){var t=n[a];if(void 0!==t)return t.exports;var e=n[a]={exports:{}};return o[a](e,e.exports,r),e.exports}(()=>{var o=r(669);o((function(n){n(document.body).on("click","#unicpo_onboarding_import",(function(r){r.stopPropagation();var a=n('[name="cpo_security"]').val(),t=n("#post_ID").val(),e=n("#unicpo_onboarding_configs").val(),c=new FormData;return c.append("security",a),c.append("pid",t),c.append("cfg_name",e),c.append("action","uni_cpo_onboarding_import"),t&&o.ajax({type:"POST",url:ajaxurl,data:c,contentType:!1,processData:!1,beforeSend:function(){},success:function(o){o.success?o.data.product_url&&window.location.replace(o.data.product_url):alert(o.data.error)},complete:function(){}}),!1}))}))})()})();