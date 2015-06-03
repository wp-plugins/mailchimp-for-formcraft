<?php

	/*
	Plugin Name: FormCraft MailChimp Add-On
	Plugin URI: http://formcraft-wp.com/addons/mailchimp/
	Description: MailChimp Add-On for FormCraft
	Author: nCrafts
	Author URI: http://formcraft-wp.com/
	Version: 1.0.9
	Text Domain: formcraft-mailchimp
	*/

	global $fc_meta, $fc_forms_table, $fc_submissions_table, $fc_views_table, $fc_files_table, $wpdb;
	$fc_forms_table = $wpdb->prefix . "formcraft_3_forms";
	$fc_submissions_table = $wpdb->prefix . "formcraft_3_submissions";
	$fc_views_table = $wpdb->prefix . "formcraft_3_views";
	$fc_files_table = $wpdb->prefix . "formcraft_3_files";

	add_action('formcraft_after_save', 'formcraft_mailchimp_trigger', 10, 4);
	function formcraft_mailchimp_trigger($content, $meta, $raw_content, $integrations)
	{
		global $fc_final_response;
		if ( in_array('MailChimp', $integrations['not_triggered']) ){ return false; }
		$mailchimp_data = formcraft_get_addon_data('MailChimp', $content['Form ID']);
		$double = isset($mailchimp_data['double_opt_in']) && $mailchimp_data['double_opt_in']==true ? true : false;
		$welcome = isset($mailchimp_data['welcome_email']) && $mailchimp_data['welcome_email']==true ? true : false;

		if (!$mailchimp_data){return false;}
		if (!isset($mailchimp_data['validKey']) || empty($mailchimp_data['validKey']) ){return false;}
		if (!isset($mailchimp_data['Map'])){return false;}

		$submit_data = array();
		foreach ($mailchimp_data['Map'] as $key => $line) {
			$submit_data[$line['listID']]['id'] = $line['listID'];
			$submit_data[$line['listID']]['double_optin'] = $double;
			$submit_data[$line['listID']]['send_welcome'] = $welcome;
			if ($line['columnID']=='EMAIL')
			{
				$email = fc_template($content, $line['formField']);
				if ( !filter_var($email,FILTER_VALIDATE_EMAIL) ) { continue; }
				$submit_data[$line['listID']]['email']['email'] = $email;
			}
			else
			{
				$submit_data[$line['listID']]['merge_vars'][$line['columnID']] = fc_template($content, $line['formField']);
				$submit_data[$line['listID']]['merge_vars'][$line['columnID']] = trim(preg_replace('/\s*\[[^)]*\]/', '', $submit_data[$line['listID']]['merge_vars'][$line['columnID']]));
			}
		}
		require_once('MailChimp.php');
		foreach ($submit_data as $key => $list_submit) {
			if (!isset($list_submit['email']))
				{$fc_final_response['debug']['failed'][] = __('MailChimp: No Email Specified','formcraft-mailchimp');continue;}
			$mailchimp = new \Drewm\MailChimp($mailchimp_data['validKey']);
			$result = $mailchimp->call('lists/subscribe', $list_submit);
			if ( isset($result['error']) )
			{
				$fc_final_response['debug']['failed'][] = __($result['error'],'formcraft-mailchimp');
			}
			else
			{
				$fc_final_response['debug']['success'][] = 'MailChimp Added: '.implode(', ', $list_submit['email']);
			}
		}
	}

	add_action('formcraft_addon_init', 'formcraft_mailchimp_addon');
	add_action('formcraft_addon_scripts', 'formcraft_mailchimp_scripts');

	function formcraft_mailchimp_addon()
	{
		register_formcraft_addon('MC_printContent',142,'MailChimp','MailChimpController',plugins_url('assets/logo.png', __FILE__ ),plugin_dir_path( __FILE__ ).'templates/',1);
	}
	function formcraft_mailchimp_scripts()
	{
		wp_enqueue_script('fcm-main-js', plugins_url( 'assets/builder.js', __FILE__ ));
		wp_enqueue_style('fcm-main-css', plugins_url( 'assets/builder.css', __FILE__ ));
	}

	add_action( 'wp_ajax_formcraft_mailchimp_test_api', 'formcraft_mailchimp_test_api' );
	function formcraft_mailchimp_test_api()
	{
		$key = $_GET['key'];
		require_once('MailChimp.php');
		$mailchimp = new \Drewm\MailChimp($key);
		$ping = $mailchimp->call('helper/ping');
		if ($ping)
		{
			echo json_encode(array('success'=>'true'));
			die();
		}
		else
		{
			echo json_encode(array('failed'=>'true'));
			die();
		}
	}
	add_action( 'wp_ajax_formcraft_mailchimp_get_lists', 'formcraft_mailchimp_get_lists' );
	function formcraft_mailchimp_get_lists()
	{
		$key = $_GET['key'];
		require_once('MailChimp.php');
		$mailchimp = new \Drewm\MailChimp($key);
		$lists = $mailchimp->call('lists/list');
		if ($lists)
		{
			echo json_encode(array('success'=>'true','lists'=>$lists['data']));
			die();
		}
		else
		{
			echo json_encode(array('failed'=>'true'));
			die();
		}
	}
	add_action( 'wp_ajax_formcraft_mailchimp_get_columns', 'formcraft_mailchimp_get_columns' );
	function formcraft_mailchimp_get_columns()
	{
		$key = $_GET['key'];
		$id = $_GET['id'];
		require_once('MailChimp.php');
		$mailchimp = new \Drewm\MailChimp($key);
		$columns = $mailchimp->call('lists/merge-vars', array('apiKey'=>$key,'id'=>array($id)));
		if ($columns)
		{
			echo json_encode(array('success'=>'true','columns'=>$columns['data'][0]['merge_vars']));
			die();
		}
		else
		{
			echo json_encode(array('failed'=>'true'));
			die();
		}
	}

	function MC_printContent()
	{

		?>
		<div id='mc-cover' id='mc-valid-{{Addons.MailChimp.showOptions}}'>
			<div class='loader'>
				<div class="fc-spinner small">
					<div class="bounce1"></div><div class="bounce2"></div><div class="bounce3"></div>
				</div>
			</div>
			<div class='help-link'>
				<a class='trigger-help' data-post-id='19'><?php _e('how does this work?','formcraft-mailchimp'); ?></a>
			</div>
			<div class='api-key hide-{{Addons.MailChimp.showOptions}}'>	
				<input placeholder='<?php _e('Enter API Key','formcraft-mailchimp') ?>' style='width: 77%; margin-right: 3%; margin-left:0' type='text' ng-model='Addons.MailChimp.api_key'><button ng-click='testKey()' style='width: 20%' class='button blue'><?php _e('Check','formcraft-mailchimp') ?></button>
			</div>
			<div ng-show='Addons.MailChimp.showOptions'>
				<div id='mapped-mc' class='nos-{{Addons.MailChimp.Map.length}}'>
					<div>
						<?php _e('Nothing Here','formcraft-mailchimp') ?>
					</div>
					<table cellpadding='0' cellspacing='0'>
						<tbody>
							<tr ng-repeat='instance in Addons.MailChimp.Map'>
								<td style='width: 30%'>
									<span>{{instance.listName}}</span>
								</td>
								<td style='width: 30%'>
									<span>{{instance.columnName}}</span>
								</td>
								<td style='width: 30%'>
									<span><input type='text' ng-model='instance.formField'/></span>
								</td>
								<td style='width: 10%; text-align: center'>
									<i ng-click='removeMap($index)' class='icon-cancel-circled'></i>
								</td>								
							</tr>
						</tbody>
					</table>
				</div>
				<div id='mc-map'>
					<select class='select-list' ng-model='SelectedList'><option value='' selected="selected"><?php _e('List','formcraft-mailchimp') ?></option><option ng-repeat='list in MCLists' value='{{list.id}}'>{{list.name}}</option></select>

					<select class='select-column' ng-model='SelectedColumn'><option value='' selected="selected"><?php _e('Column','formcraft-mailchimp') ?></option><option ng-repeat='col in MCColumns' value='{{col.tag}}'>{{col.name}}</option></select>

					<input class='select-field' type='text' ng-model='FieldName' placeholder='<?php _e('Form Field','formcraft-mailchimp') ?>'>
					<button class='button' ng-click='addMap()'><i class='icon-plus'></i></button>
				</div>
				<div class='more-options'>
					<label><input type='checkbox' ng-model='Addons.MailChimp.double_opt_in'><?php _e('Double Opt-In','formcraft-mailchimp'); ?></label>
					<label><input type='checkbox' ng-model='Addons.MailChimp.welcome_email'><?php _e('Send Welcome Email','formcraft-mailchimp'); ?></label>
				</div>
			</div>
		</div>
		<?php
	}


	?>