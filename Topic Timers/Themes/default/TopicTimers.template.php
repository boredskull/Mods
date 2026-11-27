<?php
if (!defined('SMF')) die('No direct access');
function template_btt_scheduler()
{
 global $context,$scripturl;
 $s=$context['btt_scheduler']; $now=time();
 $state=!$s['enabled']?'Topic Timers disabled':(!$s['exists']?'Scheduled task missing':($s['disabled']?'Scheduled task disabled':($s['next']>0 && $s['next']<$now?'Scheduled task due / awaiting SMF':'Scheduled task enabled')));
 echo '<div class="roundframe"><dl class="settings"><dt>Current UTC time (page load)</dt><dd>',gmdate('Y-m-d H:i:s',$now),' UTC</dd><dt>Scheduler status</dt><dd>',$state,'</dd><dt>Last logged task run (UTC)</dt><dd>',$s['last']?gmdate('Y-m-d H:i:s',$s['last']):'No run recorded','</dd><dt>Next scheduled check (UTC)</dt><dd>',(!$s['disabled'] && $s['next'])?gmdate('Y-m-d H:i:s',$s['next']):'Unavailable','</dd></dl><p>SMF checks timers when its scheduler runs. Due times are not guaranteed execution times. Refresh this page to update the diagnostics.</p>';
 if (!empty($context['user']['is_admin'])) echo '<p><a href="',$scripturl,'?action=admin;area=scheduledtasks">Manage scheduled tasks / Run now</a></p>';
 echo '</div>';
}
function BTT_status_display($row,$now)
{
 $status=$row['status'];
 if ($status==='pending' && (int)$row['execute_at']<=$now) $status='overdue';
 $labels=array('pending'=>'Pending','overdue'=>'Overdue','done'=>'Completed','failed'=>'Failed','cancelled'=>'Cancelled','processing'=>'Processing');
 $colors=array('pending'=>'#475569','overdue'=>'#92400e','done'=>'#166534','failed'=>'#991b1b','cancelled'=>'#475569','processing'=>'#1e40af');
 $backgrounds=array('pending'=>'#f1f5f9','overdue'=>'#fef3c7','done'=>'#dcfce7','failed'=>'#fee2e2','cancelled'=>'#f1f5f9','processing'=>'#dbeafe');
 return '<span style="display:inline-block;padding:3px 8px;border-radius:4px;font-weight:bold;color:'.($colors[$status]??'#475569').';background:'.($backgrounds[$status]??'#f1f5f9').'">'.htmlspecialchars($labels[$status]??ucfirst($status),ENT_QUOTES,'UTF-8').'</span>';
}
function template_btt_admin()
{
 global $context,$scripturl;
 $s=$context['btt_settings'];
 echo '<div class="cat_bar"><h3 class="catbg">Scheduler diagnostics</h3></div>';
 template_btt_scheduler();
 echo '<div class="cat_bar"><h3 class="catbg">Topic Timers</h3></div><div class="roundframe"><p>Scheduled actions are checked approximately once per minute by SMF scheduled tasks. All times are UTC.</p><form method="post" action="',$scripturl,'?action=admin;area=modsettings;sa=btt"><dl class="settings"><dt>Enable Topic Timers</dt><dd><input type="checkbox" name="btt_enabled" value="1"', $s['enabled']?' checked':'','></dd><dt>Allow administrators to schedule topic removal</dt><dd><input type="checkbox" name="btt_delete_enabled" value="1"',$s['delete']?' checked':'','></dd><dt>Recheck creator&#039;s permissions when timers execute</dt><dd><input type="checkbox" name="btt_recheck_permissions" value="1"', $s['recheck']?' checked':'','></dd></dl><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><button class="button" name="btt_save" value="1" type="submit">Save settings</button></form></div>';
 echo '<div class="cat_bar"><h3 class="catbg">Failed timers (latest 100)</h3></div><div class="roundframe"><table class="table_grid" style="width:100%"><thead><tr><th>Topic</th><th>Action</th><th>Scheduled (UTC)</th><th>Error</th><th></th></tr></thead><tbody>';
 if (empty($context['btt_failed'])) echo '<tr><td colspan="5">No failed timers.</td></tr>';
 foreach ($context['btt_failed'] as $item)
 {
  echo '<tr><td>',(int)$item['id_topic'],'</td><td>',htmlspecialchars($item['action'],ENT_QUOTES,'UTF-8'),'</td><td>',gmdate('Y-m-d H:i',(int)$item['execute_at']),'</td><td>',htmlspecialchars($item['error_message'],ENT_QUOTES,'UTF-8'),'</td><td><form method="post" action="',$scripturl,'?action=admin;area=modsettings;sa=btt"><input type="hidden" name="btt_retry_failed" value="1"><input type="hidden" name="id_timer" value="',(int)$item['id_timer'],'"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><button type="submit" class="button">Retry</button></form></td></tr>';
 }
 echo '</tbody></table></div>';
}
function template_btt_list()
{
 global $context,$scripturl, $smcFunc;
 $info=$context['btt_topic'];$id=(int)$info['id_topic'];
 template_btt_scheduler();
 echo '<div class="cat_bar"><h3 class="catbg">Topic Timers · Topic #',$id,'</h3></div><div class="roundframe"><p>Choose what happens and when. Times are entered in UTC. Only members with appropriate moderation permissions can create timers. Topic deletion requires administrator access and explicit enablement.</p>';
 echo '<form method="post" action="',$scripturl,'?action=topictimers;sa=save"><dl class="settings"><dt>Action</dt><dd><select name="timer_action" id="btt_action">';
 foreach($context['btt_allowed'] as $act) echo '<option value="',htmlspecialchars($act,ENT_QUOTES,'UTF-8'),'">',htmlspecialchars(($act==='pin'?'Pin (Make sticky)':($act==='unpin'?'Unpin (Remove sticky)':ucfirst($act))),ENT_QUOTES,'UTF-8'),'</option>';
 echo '</select></dd><dt>Destination board (for Move)</dt><dd><select name="target_board"><option value="0">Select a board</option>';
 foreach($context['btt_boards'] as $b) echo '<option value="',(int)$b['id_board'],'">',htmlspecialchars($b['name'],ENT_QUOTES,'UTF-8'),'</option>';
 echo '</select></dd><dt>Execute at (UTC)</dt><dd><input type="datetime-local" name="execute_at" required value="',gmdate('Y-m-d\TH:i',time()+3600),'"></dd></dl>';
 echo '<input type="hidden" name="topic" value="',$id,'"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><button type="submit" class="button">Schedule timer</button></form></div>';
 echo '<div class="cat_bar"><h3 class="catbg">Timer history</h3></div><div class="roundframe"><table class="table_grid" style="width:100%"><thead><tr><th scope="col" style="text-align:left">Action</th><th scope="col" style="text-align:left">When (UTC)</th><th scope="col" style="text-align:left">Status</th><th scope="col" style="text-align:left">Details</th><th scope="col" style="text-align:right">Options</th></tr></thead><tbody>';
 if (empty($context['btt_rows'])) echo '<tr><td colspan="5">No timers for this topic.</td></tr>';
 $i=0; $now=time();
 foreach($context['btt_rows'] as $row)
 {
  echo '<tr class="',($i++%2?'windowbg2':'windowbg'),'"><td>',htmlspecialchars(ucfirst($row['action']),ENT_QUOTES,'UTF-8'),'</td><td>',gmdate('Y-m-d H:i',(int)$row['execute_at']),'</td><td>',BTT_status_display($row,$now),'</td><td>',htmlspecialchars($row['error_message'],ENT_QUOTES,'UTF-8');
  if ($row['status']==='pending' && (int)$row['retry_at']>$now) echo '<div>Retry after ',gmdate('Y-m-d H:i:s',(int)$row['retry_at']),' UTC</div>';
  echo '</td><td style="text-align:right">';
  if ($row['status']==='pending' && ((int)$row['id_member']===(int)$context['user']['id'] || !empty($context['user']['is_admin'])))
  {
   echo '<form method="post" action="',$scripturl,'?action=topictimers;sa=cancel;topic=',$id,'"><input type="hidden" name="id_timer" value="',(int)$row['id_timer'],'"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><button type="submit" class="button">Cancel</button></form>';
  }
  if ($row['status']==='failed' && !empty($context['user']['is_admin'])) echo '<form method="post" action="',$scripturl,'?action=topictimers;sa=retry;topic=',$id,'"><input type="hidden" name="id_timer" value="',(int)$row['id_timer'],'"><input type="hidden" name="',$context['session_var'],'" value="',$context['session_id'],'"><button type="submit" class="button">Retry</button></form>';
  echo '</td></tr>';
 }
 echo '</tbody></table></div>';
}
