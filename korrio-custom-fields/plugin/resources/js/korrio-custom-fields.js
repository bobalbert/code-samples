kcf = {}; // Namespace for our global variables.
kcf.names = []; // The existing fields & their names. Populated when loading data for the edit form; used to validate for unique names.

$(document).ready(function(){
    //alert("init");
	kcf.validate = new KorrioValidator();
	kcf.$validateHolder = $('#edit-customfield-form');
	korrio_customfields_init_slide_form();

	$('.info-help').mouseover(function(){
		var helpPop = $(this).children('.help-popup');
		var iconPos = $(this).position(); //coords of the help icon
		var pWidth = 645; //width of the parent div in which flyout should render
	    var popWidth = $(this).children('.help-popup').width(); //width of the popup
		if(popWidth > (pWidth - iconPos.left)) {
			helpPop.css({'left':'-285px'});
			helpPop.children('.b').addClass('b-rt');
		}		 

		helpPop.show();
	});

	$('.info').mouseout(function(){
		$(this).children('.help-popup').hide();
	});	

	
	if(window.location.search=="?kamcmd=ca") {
		setTimeout(function(){ $('.create-customfield').click(); },500);
	}
	$('.help-icon').popover();
	
	// hide show the radio options
	$('.cf_type_form').live('change',function() {
		if($(this).val()=='radio') {
			$('#cf_radio_cont').show();
		}else {
			$('#cf_radio_cont').hide();
		}
	});

	// add new radio option form field
	$('.cf_create_radio_opt_btn').live('click',function() {
		//new_radio_option='<div class="cf_radio_opt_cont"><label class="cf_radio_opt_lbl">Radio Option:</label><input class="cf_radio_opt_form" type="text" name="radio_option[]"/><span class="close cf_radio_opt_del">x</span></div>';//cf_radio_opt.clone();

        new_radio_option = '<div class="content-group span10 cf_radio_opt_cont"><div class="control-group span4">\
            <label class="control-label">Radio Option Label\
                <a class="help-icon"\
                data-content="The radio option label is the text that is shown to the user. This can be updated after created." \
                rel="popover" \
                href="#" \
                data-original-title="Radio Option Label">\
                    <i class="icon-question-sign"></i>\
                </a>\
        </label>\
            <div class="controls">\
                <input name="radio_option['+ radioNumb +'\][label]" type="text" class="span4 required" maxlength="100" value="" />\
            </div>\
        </div>\
        <div class="control-group span4">\
            <label class="control-label">Radio Option Value\
                <a class="help-icon"\
                data-content="The radio option value is the answer/value that will be stored when a user chooses the option. Once set, this can not be updated."\
                rel="popover"\
                href="#"\
                data-original-title="Radio Option Value">\
                    <i class="icon-question-sign"></i>\
                </a>\
            </label>\
            <div class="controls">\
                <input name="radio_option['+ radioNumb +'\][value]" type="text" class="span4 required" maxlength="100" value="" />\
            </div>\
        </div>\
        <div class="control-group span1">\
            <button class="btn btn-mini btn-danger cf_radio_opt_del">X</button>\
        </div>\
        </div>';

        $('#cf_radio_opt_add').append(new_radio_option);
        radioNumb++;
        $('.help-icon').popover();
		return false;
	});
	
	// delete radio option form field
	$('.cf_radio_opt_del').live('click',function() {
		if(confirm('Are you sure you want to delete this radio option?')) {
			$(this).parents('.cf_radio_opt_cont').remove();
		}
	});


    $('#show-deleted').click(function(ev){
           $('.inactive_customfields_table').toggle();
            $(this).html(($('#show-deleted').text() == 'Show Deleted') ? 'Hide Deleted' : 'Show Deleted');
    });


    var _seenDeleteNotice = false;
    // set inactive custom field
    $('.all-customfields').on('click', '.inactive-customfield', function(event){
        //showLoading("Deleting Adjustment Data");
        if(!_seenDeleteNotice){
            if(!confirm("Note: Programs that have this custom field assigned will still show during registration.\n\nIf you would like it remove from the registration, edit the actual program.")) {
                _seenDeleteNotice = true;
                return true;
            }

            _seenDeleteNotice = true;
        }

        $('#action').val('inactive');
        $(".load","#statusModal").text('Setting Inactive');
        $("#statusModal").modal({backdrop:'static',keyboard:false,show:true});
        var _CustomFieldId = $(this).attr("id");

        $.ajax(
            {
                type: "GET",
                url: '/?korrio_customfields_edit_customfields_ajax&customfield_id=' + _CustomFieldId + '&action=inactive',
                success: function(result)
                {
                    result = JSON.parse(result);
                    $("#customfield_row_"+_CustomFieldId).remove();
                    update_customfield_table(result);

                    setTimeout(function() { $("#statusModal").modal("hide"); },300);
                }
            });
    });

    // edit custom field
    $('.all-customfields').on('click', '.edit-customfield-details', function(){

        $('.all-customfields').hide();

        //showLoading("Retrieving Data");
        $(".load","#statusModal").text('Retrieving Data');
        $("#statusModal").modal({backdrop:'static',keyboard:false,show:true});

        reset_customfields_form();
        $('#customfield-form-title').html("<h3>Edit Custom Field</h3>");

        var _CustomfieldId = $(this).attr("id");

		$.ajax(
			{
				type: "GET",
				url: '/?korrio_customfield_details_ajax&id=' + _CustomfieldId,
				success: function(result)
				{
					populate_customfields_form(JSON.parse(result));
					
					setTimeout(function() { $("#statusModal").modal("hide"); },300);
				}
			});

		$('.edit-customfields-holder').show();
		return false;
    });

    // delete custom field
    $('.all-customfields').on('click', '.delete-customfield', function(){
        //showLoading("Deleting Adjustment Data");
        $(".load","#statusModal").text('Deleting Custom Field Data');
        $("#statusModal").modal({backdrop:'static',keyboard:false,show:true});
        var _CustomFieldId = $(this).attr("id");

        $.ajax(
            {
                type: "GET",
                url: '/?korrio_customfields_edit_customfields_ajax&customfield_id=' + _CustomFieldId + '&action=deleted',
                success: function(result)
                {
                    $("#customfield_row_"+_CustomFieldId).remove();

                    setTimeout(function() { $("#statusModal").modal("hide"); },300);
                }
            });
        return false;
    });

    // set inactive custom field
    $('.all-customfields').on('click', '.activate-customfield', function(){
        //showLoading("Deleting Adjustment Data");
        $('#action').val('active');
        $(".load","#statusModal").text('Setting Active');
        $("#statusModal").modal({backdrop:'static',keyboard:false,show:true});
        var _CustomFieldId = $(this).attr("id");

        $.ajax(
            {
                type: "GET",
                url: '/?korrio_customfields_edit_customfields_ajax&customfield_id=' + _CustomFieldId + '&action=active',
                success: function(result)
                {
                    result = JSON.parse(result);
                    $("#customfield_row_"+_CustomFieldId).remove();
                    update_customfield_table(result);

                    setTimeout(function() { $("#statusModal").modal("hide"); },300);
                }
            });
        return false;
    });

});

var customfields_navon;
/**
* This will initialize all the form operations for the customfield page.
*/
function korrio_customfields_init_slide_form()
{
	$.ajax(
		{
			type: "GET",
			url: '/?korrio_customfields_get_program_customfield_names_ajax&program_id=' + $('input#group_id').val(),
			success: function(result)
			{
				kcf.names = JSON.parse(result);
			}
		});

	//gets the default id for setting program/team active highlighting. 
	customfields_navon = "#"+$('.action-list > li.active').attr('id');

	// Setup tabs support
	var customfields_edit_details=$('div.edit-customfield-form-holder');
	var customfields_edit_details_tabs;
	
	if(customfields_edit_details.length) {
		customfields_edit_details_tabs=customfields_edit_details.tabs();
	}
	
	$('.create-customfield').click(function()
	{
		reset_customfields_form();

		$('.all-customfields').hide();
		
        $('#customfield-form-title').html("<h3>Create Custom Field</h3>");
		//program/team nav item highlighting
		$(this).parent().siblings('li').removeClass("active");
		$('#create_customfield').addClass("active");
		
		$('.edit-customfields-holder').show();
		setTimeout('window.scrollTo(0,0)',500);	//settimeout = hack for chrome/js timing issue.
	});

	$('.customfields-cancel').click(function(){
		hide_customfields_form();
		reset_customfields_form();
		$('.all-customfields').show();
	});	
	
	
	$('.customfields-save').click(function()
	{
		//alert('about to call save...');
		save_customfields_form();
	});	

}

// hide the custom fields form
function hide_customfields_form()
{
	$('.edit-customfields-holder').hide();
	
	//program/team nav item highlighting
	$('#create_customfield').removeClass("active");
	
	$(customfields_navon).addClass("active");
	
	scroll(0,0);
}

// save form data to db
function save_customfields_form()
{
	// Validations.
    if(!$("#edit-customfield-form").valid()){
        return false;
    };
	var nErrs = validate_customfields();
	if (nErrs) {
		return false;
	}
	
    $("#edit-customfield-form").find(':input').removeAttr('disabled');
    $('#customfield_type').removeAttr('disabled');

	var formData = $('#edit-customfield-form').serialize();

	//alert('formData='+formData);

	//showLoading('Saving Adjustment Details');
	$(".load","#statusModal").text('Saving Custom Field Details')
	$("#statusModal").modal("show");
	$.ajax({
		type: "POST",
		url: "/?korrio_customfields_edit_customfields_ajax",
		data: formData,
		dataType: 'json',
		success: function(result) {
			update_customfield_table(result);
			hide_customfields_form();
			$('.all-customfields').show();
			reset_customfields_form();
			$("#statusModal").modal("hide");
		}
	});
	return false;
}

// handles the redrawing of rows in the list table
function update_customfield_table(result)
{
	action			= $('#action').val();
	customfield_id	= result.id;
	name			= result.title;
	text 			= result.text;
	type 			= result.type;

    if(result.is_visible == '1'){
        is_visible = "Yes";
    }else{
        is_visible = "No";
    }
    if(result.is_required == '1'){
        is_required = "Yes";
    }else{
        is_required = "No";
    }
	
	switch (action)
	{
        case 'new':
        case 'active':
			new_row = "<tr id='customfield_row_"+customfield_id+"' >";
			new_row += "<td class='name'>"+name+"</td>";
			new_row += "<td class='text'>"+text+"</td>";
			new_row += "<td class='type'>"+type+"</td>";
            new_row += "<td class='is_visible'>"+is_visible+"</td>";
            new_row += "<td class='is_required'>"+is_required+"</td>";
			new_row += "<td class='tools'>";
			new_row += "<button class='btn btn-small btn-success edit-customfield-details' id='"+customfield_id+"' >Edit</button> &nbsp; ";
			new_row += "<button class='btn btn-small btn-danger inactive-customfield' id='"+customfield_id+"' >Delete</button>";
			new_row += "</td>";
			new_row += "</tr>";
			$(new_row).appendTo($('table.customfields_table tbody'));
		break;

        case 'inactive':
            new_row = "<tr id='customfield_row_"+customfield_id+"' >";
            new_row += "<td class='name'>"+name+"</td>";
            new_row += "<td class='text'>"+text+"</td>";
            new_row += "<td class='type'>"+type+"</td>";
            new_row += "<td class='is_visible'>"+is_visible+"</td>";
            new_row += "<td class='is_required'>"+is_required+"</td>";
            new_row += "<td class='tools'>";
            new_row += "<button class='btn btn-small btn-success activate-customfield' id='"+customfield_id+"' >Activate</button>";
            new_row += "</td>";
            new_row += "</tr>";
            $(new_row).appendTo($('table.inactive_customfields_table tbody'));
        break;

		case 'update':
            name_cell = $('#customfield_row_'+customfield_id+' .name');
			text_cell = $('#customfield_row_'+customfield_id+' .text');
			type_cell = $('#customfield_row_'+customfield_id+' .type');
            is_visible_cell = $('#customfield_row_'+customfield_id+' .is_visible');
            is_required_cell = $('#customfield_row_'+customfield_id+' .is_required');

			name_cell.html(name);
			text_cell.html(text);
			type_cell.html(type);
            is_visible_cell.html(is_visible);
            is_required_cell.html(is_required);
		break;
	}
}

// validate name
function validate_customfields()
{
	kcf.validate.resetErrorFields (kcf.$validateHolder);
	
	var isOK     = true;
	var thisId   = $('#customfield_id').val();
	var thisName = $.trim($('#customfield_name').val()).toLowerCase();
	for (var i = 0; i < kcf.names.length; i++) {
		if (kcf.names[i].id != thisId && $.trim(kcf.names[i].name).toLowerCase() == thisName) {
			isOK = false;
			break;
		}
	}
	kcf.validate.generic($("#customfield_name"), isOK);

	return kcf.validate.errorItems.length;
}

// fill out the form with the data from DB
function populate_customfields_form(customfield)
{
	$('#action'				 ).val('update');
	$('#customfield_id'		 ).val(customfield.id);
	$('#customfield_name'		 ).val(customfield.title);
	$('#customfield_type'		 ).val(customfield.type);
	$('#customfield_text'		 ).val(customfield.text);
	$('#customfield_description').val(customfield.description);
	$('#customfield_is_visible' ).val(customfield.is_visible);
	$('#customfield_is_required').val(customfield.is_required);
    $('#customfield_type').attr('disabled', 'disabled');
	
	if(customfield.type == "radio"){
		$('#cf_radio_cont').show();

		values = JSON.parse(customfield.value);
		$.each(values, function(radioNumb, data) {
            new_radio_option = '<div class="content-group span10 cf_radio_opt_cont"><div class="control-group span4">\
            <label class="control-label">Radio Option Label\
                <a class="help-icon"\
                data-content="The radio option label is the text that is shown to the user. This can be updated after created." \
                rel="popover" \
                href="#" \
                data-original-title="Radio Option Label">\
                    <i class="icon-question-sign"></i>\
                </a>\
        </label>\
            <div class="controls">\
                <input name="radio_option['+ radioNumb +'\][label]" type="text" class="span4" maxlength="100" value="'+ data.label +'\" />\
            </div>\
        </div>\
        <div class="control-group span4">\
            <label class="control-label">Radio Option Value\
                <a class="help-icon"\
                data-content="The radio option value is the answer/value that will be stored when a user chooses the option. Once set, the can not be updated."\
                rel="popover"\
                href="#"\
                data-original-title="Radio Option Value">\
                    <i class="icon-question-sign"></i>\
                </a>\
            </label>\
            <div class="controls">\
                <input name="radio_option['+ radioNumb +'\][value]" type="text" class="span4" maxlength="100" value="'+ data.value +'" disabled="disabled" />\
            </div>\
        </div>\
        </div>';

            $('#cf_radio_opt_add').append(new_radio_option);

			//new_radio_option='<div class="cf_radio_opt_cont"><label class="cf_radio_opt_lbl">Radio Option:</label><input class="cf_radio_opt_form" type="text" name="radio_option[]" value="'+ this +'"/><span class="close cf_radio_opt_del">x</span></div>';
			//$(".cf_radio_cont").append(new_radio_option);
	   });

       $('.help-icon').popover();
	}

    radioNumb = $('.cf_radio_opt_cont').length;
}

// clear form before adding data or blank for create
function reset_customfields_form() 
{
	$('#action'					).val('new');
	$('#customfield_id'			).val('new');
	$('#customfield_name'		).val('');
	$('#customfield_type'		).val('text');
	$('#customfield_text'		).val('');
	$('#customfield_description').val('');
	$('#customfield_is_visible'	).val(1);
	$('#customfield_is_required').val(0);
    $('#customfield_type').removeAttr('disabled');
	
	$('#cf_radio_cont').hide();
	$(".cf_radio_opt_cont").each(function(){
		$(this).remove();
	});

    $("label.error").each(function(){
        $(this).remove();
    });
	kcf.validate.resetErrorFields (kcf.$validateHolder);

    radioNumb = 0;
}