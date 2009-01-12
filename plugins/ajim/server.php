<?php

$plugins->attachHook('session_started', 'ajim_page_init();');

function ajim_page_init()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $paths->add_page(array(
      'name' => 'AjIM JSON handler',
      'urlname' => 'AjimJson',
      'namespace' => 'Special',
      'visible' => 0,
      'special' => 1,
      'comments_on' => 0,
      'protected' => 0
    ));
}

function page_Special_AjimJson()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  global $lang;
  
  header('Content-type: text/javascript');
  if ( !isset($_GET['r']) && !isset($_POST['r']) )
  {
    return print enano_json_encode(array(
        'mode' => 'error',
        'error' => 'No request specified.'
      ));
  }
  $request = enano_json_decode($_REQUEST['r']);
  if ( !isset($request['mode']) )
  {
    return print enano_json_encode(array(
        'mode' => 'error',
        'error' => 'No mode specified.'
      ));
  }
  switch($request['mode'])
  {
    case 'watch':
      @set_time_limit(0);
      $time = ( !empty($request['lastrefresh']) ) ? intval($request['lastrefresh']) : 0;
      $end = microtime_float() + 59;
      // run cron-ish stuff
      if ( intval(getConfig('ajim_last_cleanout', 0)) + 86400 < time() )
      {
        $q = $db->sql_query('SELECT COUNT(message_id) FROM ' . table_prefix . "ajim2;");
        if ( !$q )
          $db->die_json();
        
        list($count) = $db->fetchrow_num();
        $db->free_result();
        if ( intval($count) > 50 )
        {
          // if there are more than 50 messages in the database, clean it out
          $limit = $count - 15;
          $q = $db->sql_query('DELETE FROM ' . table_prefix . "ajim2 ORDER BY message_time ASC LIMIT $limit;");
          if ( !$q )
            $db->die_json();
        }
        
        setConfig('ajim_last_cleanout', time());
      }
      
      while ( microtime_float() < $end )
      {
        $q = $db->sql_query('SELECT * FROM ' . table_prefix . "ajim2 WHERE message_time >= $time OR message_update_time >= $time ORDER BY message_time DESC LIMIT 30;");
        if ( !$q )
          $db->die_json();
        if ( $db->numrows() > 0 || $time == 0 )
          break;
        $db->free_result();
        usleep(500000); // 0.5s
      }
      if ( $q )
      {
        $messages = array();
        while ( $row = $db->fetchrow() )
        {
          $row['rank_info'] = $session->get_user_rank($row['user_id']);
          $row['message_html'] = RenderMan::render($row['message']);
          $row['human_time'] = enano_date('n/j, g:ia', $row['message_time']);
          $messages[] = $row;
        }
        $response = array(
          'mode' => 'messages',
          'now' => time(),
          'messages' => $messages
        );
        return print enano_json_encode($response);
      }
      else
      {
        return print enano_json_encode(array(
            'mode' => 'messages',
            'now' => time(),
            'messages' => array()
          ));
      }
      break;
    case 'submit':
      if ( !$session->get_permissions('ajim_post') )
      {
        return print enano_json_encode(array(
            'mode' => 'error',
            'error' => $lang->get('ajim_err_post_denied')
          ));
      }
      $name = $session->user_logged_in ? $session->username : $request['user'];
      $content = trim($request['message']);
      if ( empty($content) )
      {
        return print enano_json_encode(array(
            'mode' => 'error',
            'error' => $lang->get('ajim_err_no_post')
          ));
      }
      
      $now = time();
      $content_db = $db->escape($content);
      $name_db = $db->escape($name);
      
      $sql = 'INSERT INTO ' . table_prefix . "ajim2(user_id, username, message, message_time, message_update_time) VALUES\n"
                        . "  ({$session->user_id}, '$name_db', '$content_db', $now, $now);";
      if ( !$db->sql_query($sql) )
        $db->die_json();
      
      // workaround for no insert_id() on postgresql
      $q = $db->sql_query('SELECT message_id FROM ' . table_prefix . "ajim2 WHERE username = '$name_db' AND message = '$content_db' AND message_time = $now ORDER BY message_id DESC LIMIT 1;");
      if ( !$q )
        $db->die_json();
      
      list($message_id) = $db->fetchrow_num();
      $db->free_result();
      
      return print enano_json_encode(array(
          'mode' => 'messages',
          'messages' => array(array(
            'rank_info' => $session->get_user_rank($session->user_id),
            'human_time' => enano_date('n/j, g:ia'),
            'message' => $content,
            'username' => $name,
            'user_id' => $session->user_id,
            'message_time' => time(),
            'message_update_time' => time(),
            'message_id' => $message_id,
            'message_html' => RenderMan::render($content)
            ))
        ));
      break;
    case 'delete':
      if ( empty($request['message_id']) )
      {
        return print enano_json_encode(array(
            'mode' => 'error',
            'error' => 'No message_id specified.'
          ));
      }
      
      $message_id = intval($request['message_id']);
      
      if ( ( !$session->get_permissions('ajim_mod') || $session->auth_level < USER_LEVEL_CHPREF ) )
      {
        // we don't have permission according to ACLs, but try to see if we can edit our
        // own posts. if so, we can allow this to continue.
        $perm_override = false;
        if ( $session->get_permissions('ajim_edit') && $session->user_logged_in )
        {
          $q = $db->sql_query('SELECT user_id FROM ' . table_prefix . "ajim2 WHERE message_id = $message_id;");
          if ( !$q )
            $db->die_json();
          
          list($user_id) = $db->fetchrow_num();
          $db->free_result();
          if ( $user_id === $session->user_id )
          {
            $perm_override = true;
          }
        }
        if ( !$perm_override )
        {
          return print enano_json_encode(array(
              'mode' => 'error',
              'error' => $lang->get('ajim_err_access_denied')
            ));
        }
      }
      
      $now = time();
      $q = $db->sql_query('UPDATE ' . table_prefix . "ajim2 SET message = '', message_update_time = $now WHERE message_id = $message_id;");
      if ( !$q )
        $db->die_json();
      
      return print enano_json_encode(array(
          'mode' => 'delete',
          'message_id' => $message_id
        ));
      break;
    case 'update':
      if ( empty($request['message_id']) )
      {
        return print enano_json_encode(array(
            'mode' => 'error',
            'error' => 'No message_id specified.'
          ));
      }
      
      $message_id = intval($request['message_id']);
      
      if ( ( !$session->get_permissions('ajim_mod') || $session->auth_level < USER_LEVEL_CHPREF ) )
      {
        // we don't have permission according to ACLs, but try to see if we can edit our
        // own posts. if so, we can allow this to continue.
        $perm_override = false;
        if ( $session->get_permissions('ajim_edit') && $session->user_logged_in )
        {
          $q = $db->sql_query('SELECT user_id FROM ' . table_prefix . "ajim2 WHERE message_id = $message_id;");
          if ( !$q )
            $db->die_json();
          
          list($user_id) = $db->fetchrow_num();
          $db->free_result();
          if ( $user_id === $session->user_id )
          {
            $perm_override = true;
          }
        }
        if ( !$perm_override )
        {
          return print enano_json_encode(array(
              'mode' => 'error',
              'error' => $lang->get('ajim_err_access_denied')
            ));
        }
      }
      
      $message = trim(@$request['message']);
      if ( empty($message) )
      {
        return print enano_json_encode(array(
            'mode' => 'error',
            'error' => $lang->get('ajim_err_no_post')
          ));
      }
      
      $message_db = $db->escape($message);
      $now = time();
      $q = $db->sql_query('UPDATE ' . table_prefix . "ajim2 SET message = '{$message_db}', message_update_time = $now WHERE message_id = $message_id;");
      if ( !$q )
        $db->die_json();
      
      return print enano_json_encode(array(
          'mode' => 'update',
          'message_id' => $message_id,
          'message' => $message,
          'message_html' => RenderMan::render($message)
        ));
      break;
  }
}

