<?php
/** Board Downloads 1.3.0 traditional catalogue. ISC licence. */
if (!defined('SMF')) die('No direct access');
class BoredBDTraditional
{
    public static function q($sql, $p = array()) { return BoredBoardDownloads::query($sql, $p); }
    public static function rows($sql, $p = array()) { return BoredBoardDownloads::rows(self::q($sql, $p)); }
    public static function one($sql, $p = array()) { $r = self::rows($sql, $p); return $r ? $r[0] : null; }
    public static function admin() { return allowedTo('admin_forum'); }
    public static function member() { global $user_info; return (int) $user_info['id']; }
    public static function text($key, $max, $default = '') {
        $v = isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : $default;
        return BoredBoardDownloads::cut($v, $max);
    }
    public static function id($key) { return isset($_GET[$key]) && is_scalar($_GET[$key]) ? max(0,(int) $_GET[$key]) : 0; }
    public static function url($sa = '', $id = 0) { return BoredBoardDownloads::baseUrl() . ($sa === '' ? '' : ';sa=' . $sa) . ($id ? ';id=' . (int) $id : ''); }
    public static function post() { if ($_SERVER['REQUEST_METHOD'] !== 'POST') fatal_lang_error('no_access', false); checkSession('post'); }
    public static function fail($key = 'no_access') { fatal_lang_error($key, false); }
    public static function page($template, $title) { global $txt; loadLanguage('BoardDownloads'); BoredBoardDownloads::startPage($template, $txt[$title]); loadTemplate('BoardDownloadsTraditional'); }
    public static function categories($all = false) {
        $rows = self::rows('SELECT * FROM {db_prefix}bd_categories ORDER BY position, name, id_category');
        $out = array(); foreach ($rows as $r) if (($all && self::admin()) || self::categoryAccess($r)) $out[(int)$r['id_category']] = $r;
        return $out;
    }
    public static function categoryAccess($c) {
        global $user_info;
        if (self::admin()) return true;
        if ($c['groups_allowed'] === '*') return true;
        if ($c['groups_allowed'] === '') return false;
        $groups = array_map('intval', explode(',', $c['groups_allowed']));
        $viewer = self::member() ? array_map('intval', (array)$user_info['groups']) : array(-1);
        return (bool) array_intersect($groups, $viewer);
    }
    public static function category($id) { return self::one('SELECT * FROM {db_prefix}bd_categories WHERE id_category={int:id}', array('id'=>(int)$id)); }
    public static function canSubmit() { return self::admin() || (self::member() > 0 && allowedTo('bd_submit')); }
    public static function canEdit($r) { return self::admin() || (self::canSubmit() && self::member() === (int)$r['id_member']); }
    public static function entry($id, $manage = false) {
        global $txt;
        loadLanguage('BoardDownloads');
        $r = self::one('SELECT d.*, c.name AS category_name, c.groups_allowed, COALESCE(m.real_name, {string:unknown}) AS author
            FROM {db_prefix}bd_entries AS d INNER JOIN {db_prefix}bd_categories AS c ON (c.id_category=d.id_category)
            LEFT JOIN {db_prefix}members AS m ON (m.id_member=d.id_member) WHERE d.id_entry={int:id}', array('id'=>(int)$id,'unknown'=>$txt['bdt_former_member']));
        if (!$r || !self::categoryAccess($r)) return null;
        if ($manage && self::canEdit($r)) return $r;
        return $r['state']==='published' && (int)$r['publish_at']<=time() ? $r : null;
    }
    public static function requireEntry($id, $manage = false) { $r=self::entry($id,$manage); if (!$r || ($manage && !self::canEdit($r))) self::fail(); return $r; }
    public static function release($id, $entry) { return self::one('SELECT * FROM {db_prefix}bd_releases WHERE id_release={int:id} AND id_entry={int:entry} AND approved=1',array('id'=>(int)$id,'entry'=>(int)$entry)); }
    public static function releases($entry, $all = false) { return self::rows('SELECT * FROM {db_prefix}bd_releases WHERE id_entry={int:id}'.($all?'':' AND approved=1').' ORDER BY created DESC,id_release DESC',array('id'=>(int)$entry)); }
    public static function github() {
        global $modSettings,$sourcedir;
        if (empty($modSettings['bsgi_enabled']) || !is_file($sourcedir.'/GitHubIntegration.php')) return array();
        require_once($sourcedir.'/GitHubIntegration.php');
        return function_exists('bsgi_repos') && function_exists('bsgi_fetch') ? bsgi_repos() : array();
    }
    public static function dispatch($sa) {
        switch ($sa) {
            case 'catalog': case '': self::browse(); break;
            case 'entry': self::detail(); break;
            case 'dashboard': self::dashboard(); break;
            case 'edit': self::edit(); break;
            case 'release': self::upload(); break;
            case 'file': self::download(); break;
            case 'favorite': self::favorite(); break;
            case 'rate': self::rate(); break;
            case 'state': self::state(); break;
            case 'categories': self::manageCategories(); break;
            case 'githubsync': self::githubSync(); break;
            default: self::fail();
        }
    }
    public static function browse() {
        global $context, $txt;
        self::page('bdt_browse','bd_title');
        $cats=self::categories(); $cat=self::id('category');
        $context['bdt_categories']=$cats; $context['bdt_items']=array(); $context['bdt_category']=$cat;
        $q=isset($_GET['q']) && is_string($_GET['q']) ? BoredBoardDownloads::cut(trim($_GET['q']),120):'';
        $sort=isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort']:'recent';
        $orders=array('recent'=>'d.updated DESC','popular'=>'d.downloads DESC','title'=>'d.title ASC','rating'=>'rating DESC');
        if (!isset($orders[$sort])) $sort='recent';
        $fav=self::member()>0 && !empty($_GET['favorites']);
        $context['bdt_search']=$q; $context['bdt_sort']=$sort; $context['bdt_favorites']=$fav;
        $context['bdt_page']=0; $context['bdt_pages']=0;
        if (!$cats || ($cat && !isset($cats[$cat]))) return;
        $p=array('cats'=>$cat?array($cat):array_keys($cats),'now'=>time(),'published'=>'published','q'=>BoredBoardDownloads::searchPattern($q),'like_escape'=>'!','member'=>self::member(),'unknown'=>$txt['bdt_former_member']);
        $base=' FROM {db_prefix}bd_entries AS d INNER JOIN {db_prefix}bd_categories AS c ON(c.id_category=d.id_category)
          LEFT JOIN {db_prefix}members AS m ON(m.id_member=d.id_member)
          WHERE d.id_category IN ({array_int:cats}) AND d.state={string:published} AND d.publish_at<={int:now}
          AND EXISTS (SELECT 1 FROM {db_prefix}bd_releases AS r WHERE r.id_entry=d.id_entry AND r.approved=1)';
        if ($q!=='') $base.=' AND (d.title LIKE {string:q} ESCAPE {string:like_escape} OR d.description LIKE {string:q} ESCAPE {string:like_escape} OR d.tags LIKE {string:q} ESCAPE {string:like_escape})';
        if ($fav) $base.=' AND EXISTS (SELECT 1 FROM {db_prefix}bd_favorites AS f WHERE f.id_entry=d.id_entry AND f.id_member={int:member})';
        $n=self::one('SELECT COUNT(*) AS total'.$base,$p); $size=max(5,min(50,(int)BoredBoardDownloads::setting('page_size',15)));
        $pages=(int)ceil((int)$n['total']/$size); $page=min(self::id('page'),max(0,$pages-1));
        $context['bdt_page']=$page; $context['bdt_pages']=$pages; $p['offset']=$page*$size; $p['size']=$size;
        $context['bdt_items']=self::rows('SELECT d.*, c.name AS category_name, COALESCE(m.real_name,{string:unknown}) AS author,
          COALESCE((SELECT AVG(v.rating) FROM {db_prefix}bd_ratings AS v WHERE v.id_entry=d.id_entry),0) AS rating'.$base.
          ' ORDER BY d.featured DESC, '.$orders[$sort].', d.id_entry DESC LIMIT {int:size} OFFSET {int:offset}',$p);
        foreach ($context['bdt_items'] as &$item) foreach (array('title','description','tags') as $field) censorText($item[$field]);
        unset($item);
    }
    public static function detail() {
        global $context;
        $r=self::requireEntry(self::id('id'),false); $releases=self::releases($r['id_entry']); if (!$releases) self::fail();
        self::page('bdt_detail','bd_title');
        foreach (array('title','description','tags','github_notes') as $field) censorText($r[$field]);
        foreach ($releases as &$release) censorText($release['changelog']); unset($release);
        $context['bdt_entry']=$r; $context['bdt_releases']=$releases;
        $context['bdt_rating']=self::one('SELECT COUNT(*) AS total, AVG(rating) AS average FROM {db_prefix}bd_ratings WHERE id_entry={int:id}',array('id'=>(int)$r['id_entry']));
        $context['bdt_my_rating']=self::member() ? self::one('SELECT rating FROM {db_prefix}bd_ratings WHERE id_entry={int:id} AND id_member={int:member}',array('id'=>(int)$r['id_entry'],'member'=>self::member())) : null;
        $context['bdt_favorite']=self::member() && self::one('SELECT id_entry FROM {db_prefix}bd_favorites WHERE id_entry={int:id} AND id_member={int:member}',array('id'=>(int)$r['id_entry'],'member'=>self::member()));
        $context['bdt_github']=in_array($r['github_repo'],self::github(),true) && preg_match('~^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$~D',$r['github_repo']);
    }
    public static function dashboard() {
        global $context; if (!self::canSubmit()) self::fail();
        self::page('bdt_dashboard','bdt_dashboard'); $cats=self::categories(); $context['bdt_items']=array();
        $pending=isset($_GET['pending']) && $_GET['pending']==='1';
        $context['bdt_pending']=$pending;
        if (!$cats) return;
        $p=array('cats'=>array_keys($cats),'member'=>self::member(),'offset'=>min(100000,self::id('page'))*50,'pending'=>'pending');
        $context['bdt_page']=min(100000,self::id('page'));
        $context['bdt_items']=self::rows('SELECT d.*,c.name AS category_name FROM {db_prefix}bd_entries AS d INNER JOIN {db_prefix}bd_categories AS c ON(c.id_category=d.id_category)
            WHERE d.id_category IN ({array_int:cats})'.(self::admin()?'':' AND d.id_member={int:member}').($pending?' AND d.state={string:pending}':'').' ORDER BY d.updated DESC,d.id_entry DESC LIMIT 50 OFFSET {int:offset}',$p);
    }
    public static function edit() {
        global $context,$smcFunc;
        if (!self::canSubmit()) self::fail(); $id=self::id('id'); $r=$id?self::requireEntry($id,true):null;
        if ($r && $r['state']==='deleted') self::fail(); $cats=self::categories();
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            self::post(); $cat=(int) self::text('category',12); if (!isset($cats[$cat])) self::fail();
            $title=self::text('title',150); if ($title==='') self::fail('bdt_invalid');
            $repo=$r?$r['github_repo']:''; if (self::admin() && self::github()) { $repo=self::text('github_repo',205); if ($repo!=='' && !in_array($repo,self::github(),true)) self::fail('bdt_invalid'); }
            $p=array('category'=>$cat,'title'=>$title,'description'=>self::text('description',15000),'tags'=>self::text('tags',255),'repo'=>$repo,'now'=>time(),'id'=>$id,'member'=>self::member(),
              'state'=>!$r?'draft':(self::admin()?$r['state']:($r['state']==='published'?'pending':$r['state'])),'featured'=>self::admin()?(!empty($_POST['featured'])?1:0):($r?(int)$r['featured']:0));
            if ($id && $r['github_repo']!==$repo) self::q('UPDATE {db_prefix}bd_entries SET github_tag={string:empty},github_notes={string:empty} WHERE id_entry={int:id}',array('empty'=>'','id'=>$id));
            if ($id) self::q('UPDATE {db_prefix}bd_entries SET id_category={int:category},title={string:title},description={string:description},tags={string:tags},github_repo={string:repo},updated={int:now},state={string:state},featured={int:featured} WHERE id_entry={int:id}',$p);
            else {
                $smcFunc['db_insert']('insert','{db_prefix}bd_entries',array('id_category'=>'int','id_member'=>'int','title'=>'string','description'=>'string','tags'=>'string','state'=>'string','github_repo'=>'string','github_notes'=>'string','created'=>'int','updated'=>'int','featured'=>'int'),array($cat,self::member(),$title,$p['description'],$p['tags'],'draft',$repo,'',$p['now'],$p['now'],$p['featured']),array('id_entry'));
                $id=(int)$smcFunc['db_insert_id']('{db_prefix}bd_entries','id_entry');
            }
            redirectexit('action=boarddownloads;sa=release;id='.$id);
        }
        self::page('bdt_edit','bdt_edit'); $context['bdt_entry']=$r; $context['bdt_categories']=$cats; $context['bdt_repos']=self::admin()?self::github():array();
    }
    public static function storage($required = true, $candidate = null) {
        global $boarddir;
        $raw=$candidate === null ? BoredBoardDownloads::setting('storage','') : $candidate; $root=!empty($_SERVER['DOCUMENT_ROOT'])?realpath($_SERVER['DOCUMENT_ROOT']):false;
        $forum=realpath($boarddir); $dir=is_string($raw) && $raw!=='' && strpos($raw, "\0")===false && (substr($raw,0,1)==='/' || preg_match('~^[A-Za-z]:[\\\\/]~',$raw))?realpath($raw):false;
        $inside=function($child,$parent){ $child=rtrim(str_replace('\\','/',$child),'/'); $parent=rtrim(str_replace('\\','/',$parent),'/'); if (DIRECTORY_SEPARATOR==='\\') { $child=strtolower($child);$parent=strtolower($parent); } return $child===$parent || strpos($child.'/', $parent.'/')===0; };
        if (!$root || !$forum || !$dir || !is_dir($dir) || !is_readable($dir) || !is_writable($dir) || $inside($dir,$root) || $inside($dir,$forum) || $inside($root,$dir) || $inside($forum,$dir)) {
            if ($required) self::fail('bdt_storage_invalid'); return false;
        }
        return $dir;
    }
    public static function upload() {
        global $context,$smcFunc;
        $id=self::id('id'); $r=self::requireEntry($id,true); if ($r['state']==='deleted') self::fail();
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            self::post(); $dir=self::storage(); $f=isset($_FILES['file'])?$_FILES['file']:null;
            if (!$f || !isset($f['error'],$f['tmp_name'],$f['name']) || $f['error']!==UPLOAD_ERR_OK || !is_string($f['tmp_name']) || !is_string($f['name']) || !is_uploaded_file($f['tmp_name'])) self::fail('bdt_upload_failed');
            $name=basename(str_replace('\\','/',$f['name'])); $name=preg_replace('/[\x00-\x1F\x7F]/','',$name);
            $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION)); $size=filesize($f['tmp_name']);
            $deny=array('php','php3','php4','php5','php7','php8','phtml','phar','cgi','pl','asp','aspx','jsp','exe','com','bat','cmd','sh','html','htm','svg','js','htaccess','user.ini');
            $max=max(1,min(2048,(int)BoredBoardDownloads::setting('max_mb',100)))*1048576;
            if ($name==='' || strlen($name)>240 || !$size || $size>$max || !in_array($ext,BoredBoardDownloads::extensions(),true) || in_array($ext,$deny,true)) self::fail('bdt_upload_failed');
            $version=self::text('version',80); $channel=self::text('channel',12); if ($version==='' || !in_array($channel,array('alpha','beta','rc','stable'),true)) self::fail('bdt_invalid');
            $hash=hash_file('sha256',$f['tmp_name']); if (!$hash) self::fail('bdt_upload_failed');
            if (self::one('SELECT id_release FROM {db_prefix}bd_releases WHERE id_entry={int:id} AND sha256={string:hash}',array('id'=>$id,'hash'=>$hash))) self::fail('bdt_duplicate');
            $key=bin2hex(random_bytes(24)).'.bin'; $dest=$dir.DIRECTORY_SEPARATOR.$key;
            // Revoke public listing before accepting an unmoderated new release. Preserve drafts and pending entries.
            if (!self::admin() && $r['state']==='published') self::q('UPDATE {db_prefix}bd_entries SET state={string:state} WHERE id_entry={int:id}',array('state'=>'pending','id'=>$id));
            if (file_exists($dest) || !move_uploaded_file($f['tmp_name'],$dest)) self::fail('bdt_upload_failed');
            @chmod($dest,0600);
            $smcFunc['db_insert']('insert','{db_prefix}bd_releases',array('id_entry'=>'int','version'=>'string','channel'=>'string','changelog'=>'string','compat_smf'=>'string','compat_php'=>'string','filename'=>'string','storage_key'=>'string','bytes'=>'int','sha256'=>'string','created'=>'int','approved'=>'int'),
                array($id,$version,$channel,self::text('changelog',15000),self::text('compat_smf',120),self::text('compat_php',120),$name,$key,$size,$hash,time(),self::admin()?1:0),array('id_release'));
            self::q('UPDATE {db_prefix}bd_entries SET updated={int:now} WHERE id_entry={int:id}',array('now'=>time(),'id'=>$id));
            redirectexit('action=boarddownloads;sa=release;id='.$id);
        }
        self::page('bdt_release','bdt_release'); $context['bdt_entry']=$r; $context['bdt_releases']=self::releases($id,true); $context['bdt_storage']=self::storage(false); $context['bdt_github']=self::admin() && in_array($r['github_repo'],self::github(),true);
    }
    public static function downloadRecord($entryId, $fileId) {
        $entry=self::entry($entryId,true);
        if (!$entry) return null;
        $privileged=self::canEdit($entry);
        if ($entry['state']==='deleted' && !self::admin()) return null;
        $r=self::one('SELECT * FROM {db_prefix}bd_releases WHERE id_release={int:id} AND id_entry={int:entry}'.($privileged?'':' AND approved=1'),array('id'=>(int)$fileId,'entry'=>(int)$entry['id_entry']));
        return $r ? array($entry,$r,$privileged) : null;
    }
    public static function download() {
        if (!in_array($_SERVER['REQUEST_METHOD'],array('GET','HEAD'),true)) self::fail();
        $record=self::downloadRecord(self::id('id'), self::id('file'));
        if (!$record) self::fail();
        list($entry, $r, $privileged) = $record;
        if (!preg_match('/^[a-f0-9]{48}\.bin$/D',$r['storage_key'])) self::fail();
        $dir=self::storage(); $path=$dir.DIRECTORY_SEPARATOR.$r['storage_key'];
        if (is_link($path) || !is_file($path) || !is_readable($path) || filesize($path)!==(int)$r['bytes']) self::fail('bdt_upload_failed');
        $fp=fopen($path,'rb'); if (!$fp) self::fail('bdt_upload_failed');
        // No inline rendering or execution of submitted content. Count download starts, not completed transfers.
        if ($_SERVER['REQUEST_METHOD']!=='HEAD' && !$privileged) {
            self::q('UPDATE {db_prefix}bd_entries SET downloads=downloads+1 WHERE id_entry={int:id}',array('id'=>(int)$entry['id_entry']));
            self::q('UPDATE {db_prefix}bd_releases SET downloads=downloads+1 WHERE id_release={int:id}',array('id'=>(int)$r['id_release']));
        }
        while (ob_get_level()) { if (!ob_end_clean()) break; }
        $ascii=preg_replace('/[^A-Za-z0-9._-]/','_',$r['filename']);
        header('Content-Type: application/octet-stream'); header('X-Content-Type-Options: nosniff'); header('Cache-Control: private, no-store');
        header('Content-Disposition: attachment; filename="'.$ascii.'"; filename*=UTF-8\'\''.rawurlencode($r['filename'])); header('Content-Length: '.(int)$r['bytes']);
        if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
        if ($_SERVER['REQUEST_METHOD']!=='HEAD') fpassthru($fp); fclose($fp); exit;
    }
    public static function favorite() {
        global $smcFunc; self::post(); if (!self::member()) self::fail(); $r=self::requireEntry(self::id('id'));
        $p=array('id'=>(int)$r['id_entry'],'member'=>self::member());
        if (!empty($_POST['remove'])) self::q('DELETE FROM {db_prefix}bd_favorites WHERE id_entry={int:id} AND id_member={int:member}',$p);
        else $smcFunc['db_insert']('ignore','{db_prefix}bd_favorites',array('id_entry'=>'int','id_member'=>'int'),array($p['id'],$p['member']),array('id_entry','id_member'));
        redirectexit('action=boarddownloads;sa=entry;id='.$p['id']);
    }
    public static function rate() {
        global $smcFunc; self::post(); if (!self::member()) self::fail(); $r=self::requireEntry(self::id('id')); $rating=(int)self::text('rating',2);
        if ($rating<1 || $rating>5 || (int)$r['id_member']===self::member()) self::fail('bdt_invalid');
        $smcFunc['db_insert']('replace','{db_prefix}bd_ratings',array('id_entry'=>'int','id_member'=>'int','rating'=>'int'),array((int)$r['id_entry'],self::member(),$rating),array('id_entry','id_member'));
        redirectexit('action=boarddownloads;sa=entry;id='.(int)$r['id_entry']);
    }
    public static function state() {
        self::post(); $r=self::requireEntry(self::id('id'),true); $state=self::text('state',12); $publish=0;
        if (!in_array($state,self::admin()?array('draft','pending','published','deleted'):array('draft','pending'),true) || ($r['state']==='deleted' && !self::admin())) self::fail();
        if ($state==='published') {
            if (!self::releases($r['id_entry'],true)) self::fail('bdt_invalid');
            $raw=self::text('publish_at',25); if ($raw!=='') { $d=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$raw,new DateTimeZone('UTC')); if (!$d || $d->format('Y-m-d\TH:i')!==$raw) self::fail('bdt_invalid'); $publish=$d->getTimestamp(); if ($publish<0 || $publish>2147483647) self::fail('bdt_invalid'); }
        }
        if ($state==='published') self::q('UPDATE {db_prefix}bd_releases SET approved=1 WHERE id_entry={int:id}',array('id'=>(int)$r['id_entry']));
        self::q('UPDATE {db_prefix}bd_entries SET state={string:state},publish_at={int:publish},updated={int:now} WHERE id_entry={int:id}',array('state'=>$state,'publish'=>$publish,'now'=>time(),'id'=>(int)$r['id_entry']));
        redirectexit('action=boarddownloads;sa=dashboard');
    }
    public static function manageCategories() {
        global $context,$smcFunc; isAllowedTo('admin_forum');
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            self::post(); $id=self::id('id'); $name=self::text('name',120); $parent=max(0,(int)self::text('parent',12)); $raw=self::text('groups_allowed',255);
            if ($name==='' || ($raw!=='' && $raw!=='*' && !preg_match('/^-?\d+(?:\s*,\s*-?\d+)*$/D',$raw))) self::fail('bdt_invalid');
            $groups=$raw==='*'?'*':($raw===''?'':implode(',',array_unique(array_map('intval',explode(',',$raw)))));
            if ($id && !self::category($id)) self::fail();
            // Hierarchy is organisational; ACL is explicit per category, never inherited.
            $seen=array(); $ancestor=$parent; while ($ancestor) { if ($ancestor===$id || isset($seen[$ancestor]) || count($seen)>100) self::fail('bdt_invalid'); $seen[$ancestor]=true; $c=self::category($ancestor); if (!$c) self::fail('bdt_invalid'); $ancestor=(int)$c['id_parent']; }
            $position=max(0,min(100000,(int)self::text('position',12)));
            if ($id) self::q('UPDATE {db_prefix}bd_categories SET name={string:name},id_parent={int:parent},groups_allowed={string:groups},position={int:position} WHERE id_category={int:id}',array('name'=>$name,'parent'=>$parent,'groups'=>$groups,'position'=>$position,'id'=>$id));
            else $smcFunc['db_insert']('insert','{db_prefix}bd_categories',array('name'=>'string','id_parent'=>'int','groups_allowed'=>'string','position'=>'int'),array($name,$parent,$groups,$position),array('id_category'));
            redirectexit('action=boarddownloads;sa=categories');
        }
        self::page('bdt_categories','bdt_categories'); $context['bdt_categories']=self::categories(true); $context['bdt_edit_category']=self::id('id')?self::category(self::id('id')):null;
    }
    public static function githubSync() {
        self::post(); isAllowedTo('admin_forum'); $r=self::requireEntry(self::id('id'),true); if (!in_array($r['github_repo'],self::github(),true)) self::fail();
        $release=bsgi_fetch($r['github_repo'],'release',true); if (!is_array($release) || empty($release['tag_name']) || !is_string($release['tag_name'])) self::fail('bdt_github_failed');
        $tag=BoredBoardDownloads::cut($release['tag_name'],80); $notes=isset($release['body']) && is_string($release['body'])?BoredBoardDownloads::cut($release['body'],15000):'';
        self::q('UPDATE {db_prefix}bd_entries SET github_tag={string:tag},github_notes={string:notes} WHERE id_entry={int:id}',array('tag'=>$tag,'notes'=>$notes,'id'=>(int)$r['id_entry']));
        redirectexit('action=boarddownloads;sa=release;id='.(int)$r['id_entry']);
    }
    public static function catalogueInfo($id) {
        if (BoredBoardDownloads::setting('mode','board')!=='traditional' || !BoredBoardDownloads::setting('enabled',1) || !BoredBoardDownloads::canView()) return null;
        $r=self::entry((int)$id); if (!$r || !self::releases($id)) return null;
        censorText($r['title']);
        return array('id'=>(int)$id,'title'=>$r['title'],'url'=>self::url('entry',$id),'downloads'=>(int)$r['downloads']);
    }
}
