<?php
if (!defined('SMF')) die('No direct access');
function bdt_e($s) { return BoredBoardDownloads::e($s); }
function bdt_url($sa='', $id=0) { return bdt_e(BoredBDTraditional::url($sa,$id)); }
function bdt_token() { global $context; echo '<input type="hidden" name="',bdt_e($context['session_var']),'" value="',bdt_e($context['session_id']),'">'; }
function bdt_menu() {
    global $txt;
    bd_menu(); echo '<nav class="bdt-nav">';
    if (BoredBDTraditional::canSubmit()) echo '<a class="button" href="',bdt_url('dashboard'),'">',bdt_e($txt['bdt_dashboard']),'</a> <a class="button" href="',bdt_url('edit'),'">',bdt_e($txt['bdt_new']),'</a> ';
    if (BoredBDTraditional::admin()) echo '<a class="button" href="',bdt_url('categories'),'">',bdt_e($txt['bdt_categories']),'</a> ';
    if (BoredBDTraditional::member()) echo '<a class="button" href="',bdt_url('catalog'),';favorites=1">',bdt_e($txt['bdt_favorites']),'</a>';
    echo '</nav>';
}
function bdt_heading($title) { echo '<div class="cat_bar"><h3 class="catbg">',bdt_e($title),'</h3></div>'; }
function bdt_category_options($cats,$chosen=0,$all=false) {
    global $txt;
    if ($all) echo '<option value="0">',bdt_e($txt['bdt_all_categories']),'</option>';
    foreach ($cats as $id=>$c) {
        // Do not disclose hidden ancestor names; only walk the already-visible category map.
        $path=array($c['name']); $seen=array($id=>true); $parent=(int)$c['id_parent'];
        while ($parent && isset($cats[$parent]) && !isset($seen[$parent])) { $seen[$parent]=true; array_unshift($path,$cats[$parent]['name']); $parent=(int)$cats[$parent]['id_parent']; }
        echo '<option value="',(int)$id,'"', (int)$chosen===(int)$id?' selected':'','>',bdt_e(implode(' / ',$path)),'</option>';
    }
}
function template_bdt_browse() {
    global $context,$txt,$scripturl;
    bdt_menu(); bdt_heading($txt['bd_title']);
    echo '<form class="windowbg bd-search" action="',bdt_e($scripturl),'" method="get"><input type="hidden" name="action" value="boarddownloads"><input type="hidden" name="sa" value="catalog">';
    if ($context['bdt_favorites']) echo '<input type="hidden" name="favorites" value="1">';
    echo '<label>',bdt_e($txt['bd_search']),' <input type="search" name="q" maxlength="120" value="',bdt_e($context['bdt_search']),'"></label> <label>',bdt_e($txt['bdt_category']),' <select name="category">';
    bdt_category_options($context['bdt_categories'],$context['bdt_category'],true);
    echo '</select></label> <label>',bdt_e($txt['bd_sort']),' <select name="sort">';
    foreach (array('recent','popular','title','rating') as $sort) echo '<option value="',$sort,'"',$context['bdt_sort']===$sort?' selected':'','>',bdt_e($txt['bdt_sort_'.$sort]),'</option>';
    echo '</select></label> <button type="submit" class="button">',bdt_e($txt['bd_search_button']),'</button></form><div class="bd-grid">';
    foreach ($context['bdt_items'] as $r) {
        $id=(int)$r['id_entry']; echo '<article class="windowbg bd-card"><div class="bd-meta">',bdt_e($r['category_name']),$r['featured']?' · '.bdt_e($txt['bdt_featured']):'','</div><h3><a href="',bdt_url('entry',$id),'">',bdt_e($r['title']),'</a></h3><p>',bdt_e(BoredBoardDownloads::excerpt($r['description'],225)),'</p><div class="bd-meta">',bdt_e($r['author']),' · ',number_format((int)$r['downloads']),' ',bdt_e($txt['bdt_downloads']),' · ',number_format((float)$r['rating'],1),' / 5</div><a class="button" href="',bdt_url('entry',$id),'">',bdt_e($txt['bd_details']),'</a></article>';
    }
    echo '</div>'; if (!$context['bdt_items']) echo '<p class="windowbg bd-notice">',bdt_e($txt['bdt_empty']),'</p>';
    if ($context['bdt_pages']>1) {
        echo '<nav class="bd-pagination" aria-label="',bdt_e($txt['bd_pages']),'">';
        for ($i=0;$i<$context['bdt_pages'];$i++) { if ($i!==0 && $i!==$context['bdt_pages']-1 && abs($i-$context['bdt_page'])>2) continue;
            echo '<a class="button" ',($i===$context['bdt_page']?'aria-current="page" ':''),'href="',bdt_url('catalog'),';page=',$i,';category=', (int)$context['bdt_category'],';sort=',rawurlencode($context['bdt_sort']),';q=',rawurlencode($context['bdt_search']),$context['bdt_favorites']?';favorites=1':'','">',$i+1,'</a>'; }
        echo '</nav>';
    }
}
function template_bdt_detail() {
    global $context,$txt,$scripturl;
    $r=$context['bdt_entry'];$id=(int)$r['id_entry'];bdt_menu();bdt_heading($r['title']);
    echo '<article class="windowbg bd-detail"><div class="bd-meta">',bdt_e($r['category_name']),' · ',bdt_e($r['author']),' · ',number_format((int)$r['downloads']),' ',bdt_e($txt['bdt_downloads']),' · ',number_format((float)$context['bdt_rating']['average'],1),' / 5 (',(int)$context['bdt_rating']['total'],')</div><div class="bdt-prose">',nl2br(bdt_e($r['description'])),'</div><p>',bdt_e($r['tags']),'</p>';
    if (BoredBDTraditional::canEdit($r)) echo '<a class="button" href="',bdt_url('edit',$id),'">',bdt_e($txt['bdt_edit']),'</a> <a class="button" href="',bdt_url('release',$id),'">',bdt_e($txt['bdt_release']),'</a>';
    if ($context['bdt_github']) { echo '<section><h3>',bdt_e($txt['bdt_github']),'</h3><p><a href="',bdt_e('https://github.com/'.$r['github_repo'].'/releases'),'" rel="noopener noreferrer">',bdt_e($r['github_repo']),'</a> ',bdt_e($r['github_tag']),'</p><div class="bdt-prose">',nl2br(bdt_e($r['github_notes'])),'</div></section>'; }
    if (BoredBDTraditional::member()) {
        echo '<div class="bdt-actions"><form method="post" action="',bdt_url('favorite',$id),'">';bdt_token(); if ($context['bdt_favorite']) echo '<input type="hidden" name="remove" value="1">';echo '<button type="submit" class="button">',bdt_e($txt[$context['bdt_favorite']?'bdt_unfavorite':'bdt_favorite']),'</button></form>';
        if ((int)$r['id_member']!==BoredBDTraditional::member()) { echo '<form method="post" action="',bdt_url('rate',$id),'">';bdt_token();echo '<label>',bdt_e($txt['bdt_rating']),' <select name="rating">';for($i=1;$i<=5;$i++)echo '<option value="',$i,'"',((int)($context['bdt_my_rating']['rating'] ?? 0)===$i?' selected':''),'>',$i,'</option>';echo '</select></label> <button class="button" type="submit">',bdt_e($txt['bd_save']),'</button></form>'; }echo '</div>';
    }
    echo '</article>';bdt_heading($txt['bdt_history']); bdt_release_list($context['bdt_releases'],$id,true);
}
function bdt_release_list($releases,$id,$links) {
    global $txt;
    foreach ($releases as $r) {
        echo '<article class="windowbg bd-detail bdt-release"><h3>',bdt_e($r['version']),' <span class="bd-label">',bdt_e($txt['bdt_channel_'.$r['channel']] ?? $r['channel']),'</span>',(empty($r['approved'])?' <span class="bd-label">'.bdt_e($txt['bdt_state_pending']).'</span>':''),'</h3><div class="bd-meta">',bdt_e(timeformat($r['created'])),' · ',bdt_e(bd_size($r['bytes'])),' · ',number_format((int)$r['downloads']),' ',bdt_e($txt['bdt_downloads']),'</div><p>',bdt_e($r['filename']),'</p><p>',bdt_e($txt['bdt_label_smf']),' ',bdt_e($r['compat_smf']),' · ',bdt_e($txt['bdt_label_php']),' ',bdt_e($r['compat_php']),'</p><p class="bdt-hash">',bdt_e($txt['bdt_label_sha256']),' <code>',bdt_e($r['sha256']),'</code></p><div class="bdt-prose">',nl2br(bdt_e($r['changelog'])),'</div>';
        if ($links) echo '<a class="button bd-primary" href="',bdt_url('file',$id),';file=',(int)$r['id_release'],'">',bdt_e($txt['bd_download']),'</a>'; echo '</article>';
    }
}
function template_bdt_dashboard() {
    global $context,$txt;
    bdt_menu();bdt_heading($txt['bdt_dashboard']);
    $pending=!empty($context['bdt_pending']);
    echo '<p><a class="button" href="',bdt_url('dashboard'),'">',bdt_e($txt['bdt_all_statuses']),'</a> <a class="button" href="',bdt_url('dashboard'),';pending=1">',bdt_e($txt['bdt_pending_only']),'</a></p>';
    if (!$context['bdt_items']) echo '<p class="windowbg bd-notice">',bdt_e($txt['bdt_empty']),'</p>';
    foreach ($context['bdt_items'] as $r) {
        $id=(int)$r['id_entry']; echo '<article class="windowbg bd-detail"><h3>',bdt_e($r['title']),'</h3><p>',bdt_e($r['category_name']),' · ',bdt_e($txt['bdt_state_'.$r['state']] ?? $r['state']),((int)$r['publish_at']>time()?' · '.bdt_e(gmdate('Y-m-d H:i',$r['publish_at']).' UTC'):''),' · ',number_format((int)$r['downloads']),' ',bdt_e($txt['bdt_downloads']),'</p>';
        if ($r['state']!=='deleted') echo '<a class="button" href="',bdt_url('edit',$id),'">',bdt_e($txt['bdt_edit']),'</a> <a class="button" href="',bdt_url('release',$id),'">',bdt_e($txt['bdt_release']),'</a>';
        if ($r['state']==='published' && (int)$r['publish_at']<=time()) echo ' <a class="button" href="',bdt_url('entry',$id),'">',bdt_e($txt['bd_details']),'</a>';
        bdt_state_form($r); echo '</article>';
    }
    $page=isset($context['bdt_page'])?(int)$context['bdt_page']:0;
    echo '<nav class="bd-pagination">'; if ($page>0) echo '<a class="button" href="',bdt_url('dashboard'),($pending?';pending=1':''),';page=',$page-1,'">',bdt_e($txt['bdt_previous']),'</a>';if(count($context['bdt_items'])===50)echo '<a class="button" href="',bdt_url('dashboard'),($pending?';pending=1':''),';page=',$page+1,'">',bdt_e($txt['bdt_next']),'</a>';echo '</nav>';
}
function bdt_state_form($r) {
    global $txt; $admin=BoredBDTraditional::admin();if (!$admin && $r['state']==='deleted')return;
    echo '<form class="bdt-actions" method="post" action="',bdt_url('state',(int)$r['id_entry']),'">';bdt_token();echo '<label>',bdt_e($txt['bdt_status']),' <select name="state">';
    foreach ($admin?array('draft','pending','published','deleted'):array('draft','pending') as $state)echo '<option value="',$state,'"',$r['state']===$state?' selected':'','>',bdt_e($txt['bdt_state_'.$state]),'</option>';echo '</select></label>';
    if($admin)echo '<label>',bdt_e($txt['bdt_publish_at']),' <input type="datetime-local" name="publish_at" value="',((int)$r['publish_at']?gmdate('Y-m-d\TH:i',$r['publish_at']):''),'" ></label>';echo '<button class="button" type="submit">',bdt_e($txt['bd_save']),'</button></form>';
}
function template_bdt_edit() {
    global $context,$txt; $r=$context['bdt_entry']?:array('id_entry'=>0,'id_category'=>0,'title'=>'','description'=>'','tags'=>'','github_repo'=>'','featured'=>0);bdt_menu();bdt_heading($txt['bdt_edit']);
    if (!$context['bdt_categories']) {echo '<p class="windowbg bd-notice">',bdt_e($txt['bdt_no_categories']),'</p>';return;}
    echo '<form class="windowbg bd-adminform bdt-form" method="post" action="',bdt_url('edit',(int)$r['id_entry']),'">';bdt_token();echo '<label>',bdt_e($txt['bdt_category']),' <select name="category" required>';bdt_category_options($context['bdt_categories'],$r['id_category']);echo '</select></label><label>',bdt_e($txt['bdt_title']),' <input name="title" maxlength="150" required value="',bdt_e($r['title']),'" ></label><label>',bdt_e($txt['bdt_description']),' <textarea name="description" rows="8" maxlength="15000">',bdt_e($r['description']),'</textarea></label><label>',bdt_e($txt['bdt_tags']),' <input name="tags" maxlength="255" value="',bdt_e($r['tags']),'" ></label>';
    if (BoredBDTraditional::admin()) echo '<label><input type="checkbox" name="featured" value="1"',$r['featured']?' checked':'','> ',bdt_e($txt['bdt_featured']),'</label>';
    if ($context['bdt_repos']) {echo '<label>',bdt_e($txt['bdt_github']),' <select name="github_repo"><option value="">',bdt_e($txt['bdt_none']),'</option>';foreach($context['bdt_repos'] as $repo)echo '<option value="',bdt_e($repo),'"',$repo===$r['github_repo']?' selected':'','>',bdt_e($repo),'</option>';echo '</select></label>';}
    echo '<button type="submit" class="button">',bdt_e($txt['bd_save']),'</button></form>';
}
function template_bdt_release() {
    global $context,$txt; $r=$context['bdt_entry'];$id=(int)$r['id_entry'];bdt_menu();bdt_heading($r['title']);
    echo '<div class="windowbg bd-detail"><a class="button" href="',bdt_url('edit',$id),'">',bdt_e($txt['bdt_edit']),'</a>';bdt_state_form($r);echo '</div>';
    if ($context['bdt_github']) {echo '<form class="windowbg bd-adminform" method="post" action="',bdt_url('githubsync',$id),'">';bdt_token();echo '<p>',bdt_e($r['github_repo']),' · ',bdt_e($r['github_tag']),'</p><button type="submit" class="button">',bdt_e($txt['bdt_sync']),'</button></form>';}
    if (!$context['bdt_storage']) echo '<p class="windowbg bd-notice">',bdt_e($txt['bdt_storage_invalid']),'</p>';
    else { echo '<form class="windowbg bd-adminform bdt-form" method="post" enctype="multipart/form-data" action="',bdt_url('release',$id),'">';bdt_token();echo '<label>',bdt_e($txt['bdt_version']),' <input name="version" required maxlength="80"></label><label>',bdt_e($txt['bdt_channel']),' <select name="channel"><option value="stable">',bdt_e($txt['bdt_channel_stable']),'</option><option value="rc">',bdt_e($txt['bdt_channel_rc']),'</option><option value="beta">',bdt_e($txt['bdt_channel_beta']),'</option><option value="alpha">',bdt_e($txt['bdt_channel_alpha']),'</option></select></label><label>SMF <input name="compat_smf" maxlength="120"></label><label>PHP <input name="compat_php" maxlength="120"></label><label>',bdt_e($txt['bdt_changelog']),' <textarea name="changelog" rows="6" maxlength="15000"></textarea></label><label>',bdt_e($txt['bdt_file']),' <input type="file" name="file" required></label><button type="submit" class="button">',bdt_e($txt['bdt_upload']),'</button></form>'; }
    bdt_heading($txt['bdt_history']);bdt_release_list($context['bdt_releases'],$id,true);
}
function template_bdt_categories() {
    global $context,$txt;bdt_menu();bdt_heading($txt['bdt_categories']);
    echo '<div class="windowbg bd-detail"><ul>';foreach($context['bdt_categories'] as $id=>$c) echo '<li><a href="',bdt_url('categories',$id),'">',bdt_e($c['name']),'</a> (#',$id,') · ',bdt_e($c['groups_allowed']===''?$txt['bdt_admin_only']:$c['groups_allowed']),'</li>';echo '</ul><a class="button" href="',bdt_url('categories'),'">',bdt_e($txt['bdt_new_category']),'</a></div>';
    $r=$context['bdt_edit_category']?:array('id_category'=>0,'id_parent'=>0,'name'=>'','groups_allowed'=>'','position'=>0);
    echo '<form class="windowbg bd-adminform bdt-form" method="post" action="',bdt_url('categories',(int)$r['id_category']),'">';bdt_token();echo '<label>',bdt_e($txt['bdt_title']),' <input name="name" required maxlength="120" value="',bdt_e($r['name']),'" ></label><label>',bdt_e($txt['bdt_parent']),' <select name="parent"><option value="0">',bdt_e($txt['bdt_none']),'</option>';bdt_category_options(array_diff_key($context['bdt_categories'],array((int)$r['id_category']=>true)),$r['id_parent']);echo '</select></label><label>',bdt_e($txt['bdt_groups']),' <input name="groups_allowed" maxlength="255" value="',bdt_e($r['groups_allowed']),'" ></label><p class="smalltext">',bdt_e($txt['bdt_groups_help']),'</p><label>',bdt_e($txt['bdt_position']),' <input type="number" name="position" min="0" max="100000" value="',(int)$r['position'],'"></label><button class="button" type="submit">',bdt_e($txt['bd_save']),'</button></form>';
}
