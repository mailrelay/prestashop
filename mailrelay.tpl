{literal}
<script type="text/javascript">
var syncGroups = function() {
    if ($('#mailrelay_group').val() == '0') {
        alert('{/literal}{$please_select_a_group}{literal}');
    } else {
        $('#divMessage').removeClass('alert alert-success');
        $('#divMessage').removeClass('alert alert-danger');
        $('#divMessage').hide();
        $('#divProgressImg').show();
        $('#divProgress').html('');
        $('#mailrelay_group').prop('disabled', true);
        $('#submitButton').prop('disabled', true);
        var completed = false;
        var start = 0
        while (!completed) {
            var request = $.ajax({
                url: '',
	            type: 'POST',
	            async: false,
	            data: {mailrelay_option: 'sync', mailrelay_group: $('#mailrelay_group').val(), start: start},
	            dataType: 'json',
	            timeout: 300000,
	            success: function (response) {
	                if (response.status == 'OK') {
		                if (response.completed) {
		                    $('#divProgress').html(response.customersCount + '/' + response.customersCount);
		                    $('#divMessage').html(response.message);
		                    $('#divMessage').addClass('alert alert-success');
		                    $('#divMessage').show();
		                    completed = true;
		                } else {
		                    $('#divProgress').html(start + '/' + response.customersCount);
		                    //start += 1;//debug mode
		                    start += 10;
		                }
		            } else {
		                $('#divMessage').html('Error: ' + response.message);
		                $('#divMessage').addClass('alert alert-danger');
		                $('#divMessage').show();
                        completed = true;
		            }
                },
                error: function(x, t, m) {
                    $('#divMessage').html('Error: ' + t);
		            $('#divMessage').addClass('alert alert-danger');
		            $('#divMessage').show();
                    completed = true;
                }
            });
        }
        $('#divProgressImg').hide();
        $('#mailrelay_group').prop('disabled', false);
        $('#submitButton').prop('disabled', false);
    }
};
</script>
{/literal}
<table width="85%" cellpadding="0" cellspacing="0" border="0">
<tr>
<td colspan="2">
    {if $mailrelay_message}
    <div class="bootstrap">
        <div class="alert alert-{$mailrelay_message_type}">{$mailrelay_message}</div>
    </div>
    {/if}
    <div class="bootstrap"><div id="divMessage" class="" style="display:none;"></div></div>
</td>
</tr>
<tr>
<td width="500">
<div style="width:400px;float:left">
    <fieldset>
        <legend>{$lang.config}</legend>
        <form method="post" action=""  name="frm1">
            <input type="hidden" name="mailrelay_option" value="save_credential" />
            <div style="clear:both;padding-top:15px;text-align:left">
                <label style="width:60px">{$lang.hostname}:</label> <div style="padding-left:80px"><input type="text" name="mailrelay_hostname" size="40" value="{$mailrelay_hostname|escape}" /></div>
                <br />
                <label style="width:60px">{$lang.key}:</label> <div style="padding-left:80px"><input type="text" name="mailrelay_key" size="50" value="{$mailrelay_key|escape}" /></div>
                <br />
                <input type="submit" value="{$lang.save}" style="padding:5px" />
            </div>
        </form>
    </fieldset>
</div>
</td>

{if $has_credential == 1}
    <td valign="top"><div style="width:400px;float:left">
        <fieldset>
            <legend>{$lang.sync}</legend>
            <form method="post" action="" name="frm2">
                <label style="width:60px">{$lang.groups}:</label>
                <div style="padding-left:80px">
                    {html_options name=mailrelay_group id=mailrelay_group options=$mailrelay_groups_options selected=$mailrelay_groups_option_selected}
                </div>

                <br />
                <input id="submitButton" type="button" value="{$lang.start_sync}" style="padding:5px" onclick="syncGroups();" />
            </form>
        </fieldset>
        <br />
        <img id="divProgressImg" src="../img/admin/ajax-loader.gif" width="16" height="16" style="display:none;" />
        <span id="divProgress"></span>
    </div></td></tr>
{/if}

</table>