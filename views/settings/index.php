<form name="settings_update" id="settings_update" method="post" action="<?= base_url() ?>api/settings/modify" enctype="multipart/form-data">
<div class="content_wrap_inner">

	<div class="content_inner_top_right">
		<h3>App</h3>
		<p><?= form_dropdown('enabled', config_item('enable_disable'), $settings['youtube']['enabled']) ?></p>
		<p><a href="<?= base_url() ?>api/<?= $this_module ?>/uninstall" id="app_uninstall" class="button_delete">Uninstall</a></p>
	</div>

	<h3>Application Keys</h3>

	<p>Google requires registering your application in the <a href="https://code.google.com/apis/console/" target="_blank">Google API Console</a></p>
				
	<p><input type="text" name="client_id" value="<?= $settings['youtube']['client_id'] ?>" class="input_bigger"> Client ID</p> 
	<p><input type="text" name="client_secret" value="<?= $settings['youtube']['client_secret'] ?>" class="input_medium"> Client Secret</p>
	
	<p>YouTube require registering your application in the <a href="http://code.google.com/apis/youtube/dashboard/gwt/index.html" target="_blank">Google Code Product Dashboard</a></p>
	
	<p><input type="text" name="developer_key" value="<?= $settings['youtube']['developer_key'] ?>" class="input_bigger"> Developer Key</p>

</div>

<span class="item_separator"></span>

<div class="content_wrap_inner">

	<h3>Social</h3>

	<p>Login
	<?= form_dropdown('social_login', config_item('yes_or_no'), $settings['youtube']['social_login']) ?>
	</p>
	
	<p>Login Redirect<br>
	<?= base_url() ?> <input type="text" size="30" name="login_redirect" value="<?= $settings['youtube']['login_redirect'] ?>" />
	</p>	
	
	<p>Connections 
	<?= form_dropdown('social_connection', config_item('yes_or_no'), $settings['youtube']['social_connection']) ?>
	</p>
	
	<p>Connections Redirect<br>
	<?= base_url() ?> <input type="text" size="30" name="connections_redirect" value="<?= $settings['youtube']['connections_redirect'] ?>" />
	</p>		

</div>

<span class="item_separator"></span>

<div class="content_wrap_inner">
			
	<h3>Default Account</h3>

	<p>http://youtube.com/ <input type="text" name="default_account" value="<?= $settings['youtube']['default_account'] ?>"></p> 

	<input type="hidden" name="module" value="<?= $this_module ?>">

	<p><input type="submit" name="save" value="Save" /></p>

</div>
</form>

<?= $shared_ajax ?>