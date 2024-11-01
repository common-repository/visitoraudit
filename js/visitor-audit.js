function visitor_audit_action(id, action) {
    if (action.length > 1){
        var data = {
            'visitor_audit_id': id,        
            'action': action,
        };
        jQuery.post(ajax_object.ajax_url, data, function(response) {
            visitor_audit_modal(response);
        });
    }
}
function visitor_audit_modal(data) {
    data = "<p>"+data+"</p>";
    jQuery('#visitor_audit_modal').html(data);
    tb_show("Action","#TB_inline?height=480&width=360&inlineId=visitor_audit_modal",null);
}