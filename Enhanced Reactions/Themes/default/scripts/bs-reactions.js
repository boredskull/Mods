/* Enhanced Reactions 1.0.10: the reaction is the source of truth; no native Like dance. */
(function () {
  'use strict';
  async function readResponse(response) {
    var raw = await response.text(), result;
    try { result = JSON.parse(raw); }
    catch (_) { throw new Error('Unexpected server response (HTTP ' + response.status + '). Check SMF error log.'); }
    if (!response.ok || !result || !result.ok)
      throw new Error((result && result.error) || ('Unable to save (HTTP ' + response.status + ')'));
    return result;
  }
  document.querySelectorAll('.bsr-reactions').forEach(function (widget) {
    var opener = widget.querySelector('.bsr-open');
    var picker = widget.querySelector('.bsr-picker');
    var status = widget.querySelector('.bsr-status');
    // Position the reaction widget in SMF's Like action region where one exists.
    var id = widget.dataset.msg;
    if (/^\d+$/.test(id || '')) {
      var nativeButton = document.getElementById('msg_' + id + '_likes');
      var actionBar = widget.closest('.under_message') || (nativeButton && nativeButton.closest('.under_message'));
      var item = widget.closest('li.bsr-list-item');
      if (item && actionBar) {
        var list = actionBar.querySelector('ul.floatleft');
        if (!list) {
          list = document.createElement('ul'); list.className='floatleft bsr-reaction-row';
          actionBar.insertBefore(list,actionBar.firstChild);
        }
        list.appendChild(item);
      }
    }
    if (opener && picker) opener.addEventListener('click', function () {
      picker.hidden = !picker.hidden;
      opener.setAttribute('aria-expanded', String(!picker.hidden));
    });
    widget.querySelectorAll('[data-reaction]').forEach(function (button) {
      button.addEventListener('click', async function () {
        if (widget.dataset.busy === '1') return;
        widget.dataset.busy='1'; status.textContent='Saving…';
        var post = new URLSearchParams();
        post.set('msg',widget.dataset.msg);
        post.set('reaction',button.dataset.reaction);
        post.set(widget.dataset.sessionVar,widget.dataset.sessionId);
        try {
          var response=await fetch(widget.dataset.endpoint, {method:'POST', credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body:post.toString()});
          var data=await readResponse(response);
          widget.querySelectorAll('[data-reaction]').forEach(function (btn) {
            btn.setAttribute('aria-pressed',String(btn.dataset.reaction===data.mine));
          });
          opener.textContent=data.mine ? data.mine_label : widget.dataset.defaultLabel;
          widget.querySelector('.bsr-summary').textContent=data.summary;
          picker.hidden=true; opener.setAttribute('aria-expanded','false');
          status.textContent=data.mine?'Reaction saved':'Reaction removed';
        } catch (error) { status.textContent=error.message || 'Unable to save reaction'; }
        finally { widget.dataset.busy='0'; }
      });
    });
    var view=widget.querySelector('.bsr-view');
    if (view) view.addEventListener('click',async function () {
      var dialog=widget.querySelector('.bsr-dialog');
      if (!dialog || typeof dialog.showModal!=='function') return;
      var content=dialog.querySelector('.bsr-dialog-content');
      content.textContent='Loading…'; dialog.showModal();
      try {
        var data=await readResponse(await fetch(widget.dataset.viewEndpoint,{credentials:'same-origin',headers:{'Accept':'application/json'}}));
        content.replaceChildren();
        data.members.forEach(function(m) {
          var paragraph=document.createElement('p');
          var link=document.createElement('a');link.href=m.url;link.textContent=m.name;
          paragraph.append(link,document.createTextNode(' · '+m.reaction_label));content.appendChild(paragraph);
        });
        if (!data.members.length) content.textContent='No reactions yet.';
      } catch (_) {content.textContent='Unable to load reactions.';}
    });
    var close=widget.querySelector('.bsr-close');
    if (close) close.addEventListener('click',function(){widget.querySelector('.bsr-dialog').close();});
  });
  // Optional externally registered targets can embed their own picker.
  document.querySelectorAll('.bsr-target-reactions').forEach(function(widget) {
    widget.querySelectorAll('[data-reaction]').forEach(function(button) {
      button.addEventListener('click', async function () {
        if (widget.dataset.busy==='1') return;
        widget.dataset.busy='1';
        var status=widget.querySelector('.bsr-status');
        var post=new URLSearchParams();
        post.set('target_type',widget.dataset.type);post.set('target_id',widget.dataset.targetId);
        post.set('reaction',button.dataset.reaction);post.set(widget.dataset.sessionVar,widget.dataset.sessionId);
        try {
          var data=await readResponse(await fetch(widget.dataset.endpoint,{method:'POST',credentials:'same-origin',
            headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body:post.toString()}));
          widget.querySelector('.bsr-summary').textContent=data.summary;
          widget.querySelectorAll('[data-reaction]').forEach(function(b){b.setAttribute('aria-pressed',String(b.dataset.reaction===data.mine));});
          status.textContent=data.mine?'Reaction saved':'Reaction removed';
        } catch(e) {status.textContent=e.message||'Unable to save';}
        finally {widget.dataset.busy='0';}
      });
    });
  });
})();
