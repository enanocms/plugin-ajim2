var ajim_last_refresh = 0;

window.ajim_init = function()
{
  window.ajim_authed = ( auth_level >= USER_LEVEL_CHPREF && ajim_can_mod );
  // have it immediately pull in whatever comments the server wants us to pull
  ajim_watch(true);
  ajim_setup_textarea();
}

addOnloadHook(ajim_init);

window.ajim_watch = function(immediate)
{
  ajim_send_request({
      mode: 'watch',
      lastrefresh: immediate ? 0 : ajim_last_refresh
  });
}

window.ajim_send_request = function(request)
{
  request = ajaxEscape(toJSONString(request));
  ajaxPost(makeUrlNS('Special', 'AjimJson'), 'r=' + request, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          ajim_handle_invalid_json(response);
          return false;
        }
        response = parseJSON(response);
        ajim_handle_response(response);
      }
    });
}

window.ajim_handle_invalid_json = function(response)
{
  ajim_fail_message_list();
  load_component('l10n');
  var messages = document.getElementById('ajim_messages');
  messages.innerHTML = $lang.get('ajim_err_bad_json') + '<br />';
  messages.appendChild(document.createTextNode(response));
}

window.ajim_handle_response = function(response)
{
  switch(response.mode)
  {
    case 'messages':
      ajim_show_message_list();
      
      var noposts = document.getElementById('ajim_noposts');
      if ( noposts )
        noposts.parentNode.removeChild(noposts);
      for ( var i = response.messages.length - 1; i >= 0; i-- )
      {
        ajim_inject_message(response.messages[i]);
      }
      if ( response.now )
      {
        window.ajim_last_refresh = response.now;
        ajim_watch();
      }
      break;
    case 'delete':
      var message = document.getElementById('ajim_message:' + response.message_id);
      if ( message )
      {
        message.parentNode.removeChild(message);
      }
      ajim_show_message_list();
      break;
    case 'update':
      var message = document.getElementById('ajim_message:' + response.message_id);
      if ( message )
      {
        var inner = getElementsByClassName(message, 'div', 'ajim_message_inner')[0];
        inner.innerHTML = response.message_html;
        message.meta.message_src = response.message;
      }
      ajim_show_message_list();
      break;
    case 'error':
      ajim_fail_message_list();
      ajim_show_error(response.error);
      break;
  }
}

window.ajim_draw_message = function(message)
{
  var div = document.createElement('div');
  div.id = 'ajim_message:' + message.message_id;
  div.className = 'ajim_message';
  div.meta = {
    message_id: message.message_id,
    message_src: message.message
  };
  
  // deleted message?
  if ( message.message == '' )
    return div;
  
  // *sigh* actually draw a message.
  
  // mod buttons
    var modbuttons = document.createElement('div');
    modbuttons.className = 'ajim_modbuttons';
    if ( !window.ajim_authed && ( ( message.user_id != 1 && message.user_id != ajim_user_id ) || message.user_id == 1 ) )
      modbuttons.style.display = 'none';
    
    var edbtn = document.createElement('a');
    edbtn.href = '#';
    edbtn.className = 'ajim_btn_edit';
    edbtn.appendChild(document.createTextNode(ajim_str_edit));
    edbtn.onclick = ajim_handle_click_edit;
    modbuttons.appendChild(edbtn);
    
    modbuttons.appendChild(document.createTextNode(' | '));
    
    var delbtn = document.createElement('a');
    delbtn.href = '#';
    delbtn.className = 'ajim_btn_delete';
    delbtn.appendChild(document.createTextNode(ajim_str_delete));
    delbtn.onclick = ajim_handle_click_delete;
    modbuttons.appendChild(delbtn);
    
    div.appendChild(modbuttons);
    
  // username
    var el = message.user_id == 1 ? 'span' : 'a';
    var username = document.createElement(el);
    username.appendChild(document.createTextNode(message.username));
    username.title = message.rank_info.rank_title;
    username.setAttribute('style', message.rank_info.rank_style);
    if ( message.user_id != 1 )
      username.href = makeUrlNS('User', message.username);
    div.appendChild(username);
    
  // message header (does a clear: both)
    var messagehead = document.createElement('div');
    messagehead.className = 'ajim_messagehead';
    div.appendChild(messagehead);
    
  // date
    var date = document.createElement('div');
    date.className = 'ajim_timestamp';
    date.appendChild(document.createTextNode(message.human_time));
    div.appendChild(date);
    
  // *finally* message
    var msgdata = document.createElement('div');
    msgdata.innerHTML = message.message_html;
    msgdata.className = 'ajim_message_inner';
    div.appendChild(msgdata);
  
  // message footer
    var messagefoot = document.createElement('div');
    messagefoot.className = 'ajim_messagefoot';
    div.appendChild(messagefoot);
  
  return div;
}

window.ajim_inject_message = function(message)
{
  var drawn = ajim_draw_message(message);
  var existing = document.getElementById('ajim_message:' + message.message_id);
  if ( existing )
  {
    insertAfter(existing.parentNode, drawn, existing);
    existing.parentNode.removeChild(existing);
  }
  else
  {
    ajim_inject_object(drawn);
  }
}

window.ajim_inject_object = function(element)
{
  var error = document.getElementById('ajim_error');
  insertAfter(error.parentNode, element, error);
}

window.ajim_show_error = function(message)
{
  if ( window.ajim_error_timeout )
    window.clearTimeout(ajim_error_timeout);
  var error = document.getElementById('ajim_error');
  error.innerHTML = '';
  error.appendChild(document.createTextNode(message));
  window.ajim_error_timeout = window.setTimeout(ajim_clear_error, 5000);
}

window.ajim_clear_error = function()
{
  var error = document.getElementById('ajim_error');
  error.innerHTML = '';
}

window.ajim_handle_click_edit = function()
{
  if ( this.editing )
  {
    this.editing = false;
    
    this.firstChild.nodeValue = $lang.get('ajim_btn_edit');
    this.nextSibling.nextSibling.firstChild.nodeValue = $lang.get('ajim_btn_delete');
    
    var newsrc = this.ta.value;
    this.ta.parentNode.removeChild(this.ta);
    this.inner.style.display = 'block';
    
    if ( newsrc == '' )
      ajim_show_error($lang.get('ajim_err_no_post'));
    
    ajim_hide_message_list();
    
    ajim_send_request({
        mode: 'update',
        message_id: this.parentNode.parentNode.meta.message_id,
        message: newsrc
      });
  }
  else
  {
    load_component('l10n');
    this.firstChild.nodeValue = $lang.get('ajim_btn_save');
    this.nextSibling.nextSibling.firstChild.nodeValue = $lang.get('etc_cancel').toLowerCase();
    this.editing = true;
    
    var src = this.parentNode.parentNode.meta.message_src;
    var inner = this.parentNode.nextSibling.nextSibling.nextSibling.nextSibling;
    
    var ta = document.createElement('textarea');
    ta.value = src;
    ta.style.fontSize = '100%';
    ta.style.width = '93%';
    inner.style.display = 'none';
    this.parentNode.parentNode.appendChild(ta);
    this.ta = ta;
    this.inner = inner;
  }
  return false;
}

window.ajim_handle_click_delete = function()
{
  var editlink = this.previousSibling.previousSibling;
  if ( editlink.editing )
  {
    editlink.editing = false;
    
    editlink.firstChild.nodeValue = $lang.get('ajim_btn_edit');
    this.firstChild.nodeValue = $lang.get('ajim_btn_delete');
    
    editlink.ta.parentNode.removeChild(editlink.ta);
    editlink.inner.style.display = 'block';
  }
  else
  {
    ajim_hide_message_list();
    var message_id = this.parentNode.parentNode.meta.message_id;
    ajim_send_request({
        mode: 'delete',
        message_id: message_id
      });
  }
  return false;
}

window.ajim_handle_click_mod = function()
{
  if ( auth_level >= USER_LEVEL_CHPREF )
    return false;
  load_component('login');
  
  ajaxLogonInit(function(k)
    {
      ajaxLoginReplaceSIDInline(k, false, USER_LEVEL_CHPREF);
      window.setTimeout(function()
        {
          mb_current_obj.destroy();
          ajim_enable_mod_tools();
        }, 500);
    }, USER_LEVEL_CHPREF);
  
  return false;
}

window.ajim_enable_mod_tools = function()
{
  var mods = document.getElementsByClassName('div', 'ajim_modbuttons');
  for ( var i = 0; i < mods.length; i++ )
  {
    mods[i].style.display = 'block';
  }
  document.getElementById('ajim_mod').innerHTML = '';
}

window.ajim_setup_textarea = function()
{
  var ta = document.getElementById('ajim_message');
  if ( !ta )
    return false;
  
  ta.shift = false;
  ta.onkeypress = function(e)
  {
    if ( !e )
      e = window.event;
    if ( !e )
      return true;
    if ( typeof(e.keyCode) == undefined )
      return true;
    if ( e.keyCode == 13 )
    {
      if ( !this.shift )
      {
        ajim_submit_message();
        e.preventDefault();
        return false;
      }
    }
  }
  ta.onkeydown = function(e)
  {
    if ( !e )
      e = window.event;
    if ( !e )
      return true;
    if ( !e.keyCode )
      return true;
    if ( e.keyCode == 16 )
    {
      this.shift = true;
    }
  }
  ta.onkeyup = function(e)
  {
    if ( !e )
      e = window.event;
    if ( !e )
      return true;
    if ( !e.keyCode )
      return true;
    if ( e.keyCode == 16 )
    {
      this.shift = false;
    }
  }
}

window.ajim_submit_message = function()
{
  var ta = document.getElementById('ajim_message');
  if ( ta.value == '' )
  {
    load_component('l10n');
    ajim_show_error($lang.get('ajim_err_no_post'));
    return false;
  }
  
  var user = document.getElementById('ajim_nickname');
  if ( user.value == '' )
    user.value = 'Guest';
  
  ajim_hide_message_list();
  
  ajim_send_request({
      mode: 'submit',
      user: user.value,
      message: ta.value
    });
  
  ta.value = '';
}

window.ajim_hide_message_list = function()
{
  var messages = document.getElementById('ajim_messages');
  window.ajim_submit_whitey = whiteOutElement(messages);
}

window.ajim_show_message_list = function()
{
  if ( window.ajim_submit_whitey )
    whiteOutReportSuccess(window.ajim_submit_whitey);
  window.ajim_submit_whitey = false;
}

window.ajim_fail_message_list = function()
{
  if ( window.ajim_submit_whitey )
    whiteOutReportFailure(window.ajim_submit_whitey);
  window.ajim_submit_whitey = false;
}
