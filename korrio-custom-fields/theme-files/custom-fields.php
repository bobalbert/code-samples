<?php
if (korrio_can_view_club_subnav()) {
	?>

	<div style="height: 42px; line-height: 42px;margin-bottom:0px;" class="row transgrey boxheader">
		<h2 class="span4">Custom Fields</h2>

		<div class="span2 pull-right">
			<a rel="tooltip" class="create-customfield btn btn-warning btn-mini" href="#">Create</a>
		</div>
	</div>

	<div class="row creme_offwhite" style="margin-right:17px;">

		<?php if ( function_exists('korrio_customfields_render_edit_form' ) ) {
			korrio_customfields_render_edit_form();
		}
		?>

		<div class="all-customfields">
			<?php
			if ( function_exists('korrio_customfields_render_list_html' ) ) {
				korrio_customfields_render_list_html();
			} else {
				echo "<br/><div class='tac'>Custom Fields Feature not enabled for your account.</div>";
			}
			?>
		</div>
	</div>

	<div class="modal hide" id="statusModal">
		<div class="modal-header hide">
			<button type="button" class="close" data-dismiss="modal">×</button>
			<h3>Modal header</h3>
		</div>
		<div class="modal-body" style="text-align:center;">
			<p><span class="load">Updating Your Information.</span> <br />One Moment...<br /><img src="/wp-content/themes/korrio-v2/assets/img/loading_16x16.gif"/></p>
		</div>
		<div class="modal-footer hide">
			<a href="#" class="btn" data-dismiss="modal">Close</a>
		</div>
	</div>

<?php } else { ?>

	<div class="row transgrey boxheader" style="margin-bottom:0px">
		<div class="span10">
			<h2>Sorry, you shouldn't be here. There's nothing for you to see.</h2>
		</div>
	</div>
<?php } ?>