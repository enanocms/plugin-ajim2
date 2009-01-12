<?php

$plugins->attachHook('acl_rule_init', 'ajim_permissions_setup($session, $this);');

function ajim_permissions_setup(&$session, &$paths)
{
  $session->register_acl_type('ajim_post', AUTH_ALLOW,    'ajim_perm_post', Array(),            'All');
  $session->register_acl_type('ajim_edit', AUTH_ALLOW,    'ajim_perm_edit', Array(),            'All');
  $session->register_acl_type('ajim_mod',  AUTH_DISALLOW, 'ajim_perm_mod',  Array('ajim_post'), 'All');
}
