<?php

$plugins->attachHook('compile_template', 'ajim_compile_sidebar();');

function ajim_compile_sidebar()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  $template->add_header('<link rel="stylesheet" type="text/css" href="' . scriptPath . '/plugins/ajim/shoutbox.css" />');
  $template->add_header('<script type="text/javascript" src="' . scriptPath . '/plugins/ajim/shoutbox.js"></script>');
  $can_mod = $session->get_permissions('ajim_mod') ? 'true' : 'false';
  $template->add_header('<script type="text/javascript">
      var ajim_can_mod = ' . $can_mod . ';
      var ajim_str_edit = "' . addslashes($lang->get('ajim_btn_edit')) . '";
      var ajim_str_delete = "' . addslashes($lang->get('ajim_btn_delete')) . '";
      var ajim_str_no_posts = "' . addslashes($lang->get('ajim_msg_no_posts')) . '";
      var ajim_user_id = ' . $session->user_id . ';
    </script>');
  
  $msg_loading = $lang->get('ajim_msg_loading');
  $html = '<div class="ajim_wrapper">';
  $html .= <<<__EOF
    <div id="ajim_messages" class="ajim_messages">
      <div id="ajim_error">
      </div>
      <span class="ajim_noposts" id="ajim_noposts">
        $msg_loading
      </span>
    </div>
    <div class="ajim_form">
__EOF;
  if ( $session->get_permissions('ajim_post') )
  {
    if ( $session->user_logged_in )
    {
      $html .= '<input type="hidden" id="ajim_nickname" value="' . htmlspecialchars($session->username) . '" />';
    }
    else
    {
      $l_name = $lang->get('ajim_lbl_name');
      $l_site = $lang->get('ajim_lbl_website');
      $html .= <<<______EOF
        <table border="0" cellspacing="3">
          <tr>
            <td>
              $l_name
            </td>
            <td>
              <input type="text" class="ajim_field" id="ajim_nickname" value="Guest" />
            </td>
          </tr>
        </table>
______EOF;
    }
    $b_submit = $lang->get('ajim_btn_submit');
    $html .= '<textarea id="ajim_message" rows="2" cols="20"></textarea>';
    $html .= <<<____EOF
      <div class="ajim_submit_wrap">
        <input type="submit" id="ajim_submit" value="{$b_submit}" onclick="ajim_submit_message();" />
      </div>
____EOF;
    if ( $session->get_permissions('ajim_mod') )
    {
      $html .= '<div id="ajim_mod">';
      if ( $session->auth_level < USER_LEVEL_CHPREF )
      {
        $html .= '<a href="#" class="ajim_modlink" onclick="ajim_handle_click_mod(); return false;">' . $lang->get('ajim_btn_mod') . '</a>';
      }
      $html .= '</div>';
    }
  }
  else
  {
    $msg_nopost = $lang->get('ajim_msg_no_post');
    $html .= <<<____EOF
      $msg_nopost
____EOF;
  }
  
  $html .= '  </div>
            </div>';
  $template->sidebar_widget('AjIM Shoutbox', $html);
}
