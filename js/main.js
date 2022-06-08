/**
* 2007-2022 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2022 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/

$(document).ready(function(){
	let ts_val = $("#TAB_SELECTOR").val();
	let all_nav = $('.nav.nav-tabs li');
	$('#refresh_button').hide();
	$('#refresh_button').val('');
	if(ts_val == 'Authentication') {
		$('#refresh_button').hide();
		let ele = all_nav[all_nav.length - 3];
		jQuery(ele).find('a').trigger('click');
	}
	if(ts_val == 'Settings') {
		$('#refresh_button').show();
		let ele = all_nav[all_nav.length - 2];
		jQuery(ele).find('a').trigger('click');
	}
	if(ts_val == 'Manual Sync') {
		$('#refresh_button').show();
		let ele = all_nav[all_nav.length - 1];
		jQuery(ele).find('a').trigger('click');
		$('#module_form_submit_btn').text('Sync');
	}

    if ($('#MAILRELAY_AUTO_SYNC_off').is(':checked') == true ) {
		$('.mailrelay_groups').closest('.form-group').hide();
    }

	$('#MAILRELAY_ACCOUNT').on('keyup', function() {
		let cvalue = $(this).val();
		let nvalue = /^(?:\w+\:\/\/)?([^\/]+)([^\?]*)\??(.*)$/.exec(cvalue);
		let hostname = '';
		if(nvalue != null && nvalue.length > 1) {
			hostname = nvalue[1].split('.');
			if(hostname.length > 0) {
				$(this).val(hostname[0]);
			}
			
		}
	})

	$('#refresh_button').on('click', function(e) {
		let ts_cval = $("#TAB_SELECTOR").val();
		$('#refresh_button').val(ts_cval);
	})

    $('#MAILRELAY_AUTO_SYNC_off').on('change', function() {
        $('.mailrelay_groups').closest('.form-group').hide();
       
    })

    $('#MAILRELAY_AUTO_SYNC_on').on('change', function() {
        $('.mailrelay_groups').closest('.form-group').show();
    })
    
     $('ul.nav-tabs > li').click(function() {
		let v = $(this).find('a').text();
		$("#TAB_SELECTOR").val(v);
		if(v == 'Manual Sync') {
			$('#refresh_button').val('');
			$('#refresh_button').show();
			$('#module_form_submit_btn').text('Sync');
		} else {
			$('#refresh_button').val('');
			$('#refresh_button').hide();
			$('#module_form_submit_btn').text('Save');
		}
		if(v == 'Settings') {
			$('#refresh_button').val('');
			$('#refresh_button').show();
		}
        $('.mailrelay_groups').closest('.form-group').hide();
    })
    if ($('#MAILRELAY_AUTO_SYNC_on').is(':checked') == true ) {
         $('ul.nav-tabs > li:nth-child(2)').click(function() {
            $('.mailrelay_groups').closest('.form-group').show();
         })
    }
    $('ul.nav-tabs > li:last-child').click(function() {
        $('.mailrelay_groups').closest('.form-group').show();
    })
  
 
   
    
})