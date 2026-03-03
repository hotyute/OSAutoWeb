<?php
$pageTitle = 'Forum Structure';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
requireRole('admin');

$categories = $pdo->query('SELECT * FROM forum_categories ORDER BY sort_order ASC')->fetchAll();
$boards     = $pdo->query('SELECT * FROM forum_boards ORDER BY sort_order ASC')->fetchAll();
$boardsByCat = [];
foreach ($boards as $b) $boardsByCat[$b['category_id']][] = $b;

$csrf = generateCSRF();

require_once __DIR__ . '/../includes/header.php';
?>

<style>
.fs-cat{margin-bottom:1.5rem;border:1px solid var(--border-color);border-radius:var(--radius);overflow:hidden;background:var(--bg-card);transition:box-shadow .2s;}
.fs-cat.drag-over{box-shadow:0 0 0 2px var(--accent-green);}
.fs-cat-header{background:var(--bg-secondary);padding:.65rem 1rem;display:flex;justify-content:space-between;align-items:center;cursor:grab;gap:.5rem;flex-wrap:wrap;border-bottom:1px solid var(--border-color);}
.fs-cat-header:active{cursor:grabbing;}
.fs-cat-header .grip{font-size:1.1rem;color:var(--text-secondary);margin-right:.5rem;cursor:grab;}
.fs-cat-name{font-weight:700;color:var(--accent-green);font-size:1rem;}
.fs-cat-desc{font-size:.78rem;color:var(--text-secondary);margin-left:.5rem;}
.fs-cat-actions{display:flex;gap:.3rem;flex-wrap:wrap;flex-shrink:0;}
.fs-board{padding:.55rem 1rem .55rem 2.5rem;border-bottom:1px solid rgba(46,49,64,.4);display:flex;justify-content:space-between;align-items:center;cursor:grab;gap:.5rem;flex-wrap:wrap;transition:background .15s;}
.fs-board:last-child{border-bottom:none;}
.fs-board:active{cursor:grabbing;}
.fs-board.drag-over-board{background:rgba(57,255,20,.06);border-bottom-color:var(--accent-green);}
.fs-board .grip{font-size:.9rem;color:var(--text-secondary);margin-right:.5rem;}
.fs-board-name{font-weight:600;font-size:.92rem;}
.fs-board-desc{font-size:.75rem;color:var(--text-secondary);margin-left:.4rem;}
.fs-board-stats{font-size:.72rem;color:var(--text-secondary);}
.fs-board-actions{display:flex;gap:.3rem;flex-wrap:wrap;flex-shrink:0;}
.fs-empty{padding:1rem 2.5rem;color:var(--text-secondary);font-size:.85rem;font-style:italic;}
.fs-add-row{padding:.5rem 1rem;display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;border-top:1px solid var(--border-color);}
.fs-add-row input{flex:1;min-width:120px;padding:.35rem .6rem;border-radius:var(--radius);border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);font-size:.85rem;}
.edit-inline{background:var(--bg-secondary);border:1px solid var(--accent-green);color:var(--text-primary);padding:.2rem .5rem;border-radius:4px;font-size:.85rem;width:auto;min-width:100px;}
.fs-add-cat{margin-bottom:1.5rem;display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;}
.fs-add-cat input{flex:1;min-width:200px;padding:.5rem .75rem;border-radius:var(--radius);border:1px solid var(--border-color);background:var(--bg-secondary);color:var(--text-primary);font-size:.9rem;}
.toast{position:fixed;bottom:2rem;right:2rem;background:var(--bg-card);border:1px solid var(--accent-green);color:var(--accent-green);padding:.75rem 1.25rem;border-radius:var(--radius);font-size:.88rem;box-shadow:var(--shadow);z-index:3000;transform:translateY(100px);opacity:0;transition:transform .3s,opacity .3s;}
.toast.is-visible{transform:translateY(0);opacity:1;}
.toast.error{border-color:var(--accent-red);color:var(--accent-red);}
@media(max-width:680px){
  .fs-cat-header,.fs-board{flex-direction:column;align-items:flex-start;}
  .fs-board{padding-left:1rem;}
  .fs-add-row{flex-direction:column;}
  .fs-add-row input{width:100%;min-width:0;}
  .fs-add-cat{flex-direction:column;}
  .fs-add-cat input{width:100%;min-width:0;}
}
</style>

<div class="flex-between flex-between-mobile mb-1">
  <h1>🏗️ Forum Structure</h1>
  <div style="display:flex;gap:.4rem;flex-wrap:wrap;">
    <a href="/admin/index.php" class="btn btn-secondary btn-sm">&larr; Admin</a>
    <a href="/forum/index.php" class="btn btn-secondary btn-sm">View Forum</a>
  </div>
</div>

<p style="color:var(--text-secondary);font-size:.88rem;margin-bottom:1rem;">
  ⬆⬇ Drag categories and boards to reorder. Changes save automatically.
  Click names to edit inline.
</p>

<!-- Quick-add category -->
<div class="fs-add-cat">
  <input type="text" id="newCatName" placeholder="New category name…">
  <button class="btn btn-primary btn-sm" onclick="quickAddCat()">+ Add Category</button>
</div>

<!-- Categories container (draggable) -->
<div id="catContainer">
  <?php foreach ($categories as $cat): ?>
    <div class="fs-cat" draggable="true" data-cat-id="<?= $cat['category_id'] ?>">

      <div class="fs-cat-header">
        <div style="display:flex;align-items:center;min-width:0;">
          <span class="grip">⠿</span>
          <span class="fs-cat-name"
                contenteditable="false"
                data-field="name"
                data-cat-id="<?= $cat['category_id'] ?>"
                ondblclick="this.contentEditable='true';this.focus();"
                onblur="saveCatInline(this)"
                onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
          ><?= e($cat['name']) ?></span>
          <span class="fs-cat-desc"
                contenteditable="false"
                data-field="description"
                data-cat-id="<?= $cat['category_id'] ?>"
                ondblclick="this.contentEditable='true';this.focus();"
                onblur="saveCatInline(this)"
                onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
          ><?= e($cat['description'] ?? 'Click to add description') ?></span>
        </div>
        <div class="fs-cat-actions">
          <button class="btn btn-danger btn-sm" onclick="deleteCat(<?= $cat['category_id'] ?>)"
                  title="Delete category">🗑️</button>
        </div>
      </div>

      <!-- Boards (draggable within/between categories) -->
      <div class="fs-boards-container" data-cat-id="<?= $cat['category_id'] ?>">
        <?php
          $catBoards = $boardsByCat[$cat['category_id']] ?? [];
          if (empty($catBoards)):
        ?>
          <div class="fs-empty">No boards. Add one below or drag a board here.</div>
        <?php else: foreach ($catBoards as $b): ?>
          <div class="fs-board" draggable="true" data-board-id="<?= $b['board_id'] ?>">
            <div style="display:flex;align-items:center;min-width:0;gap:.3rem;flex-wrap:wrap;">
              <span class="grip">⋮⋮</span>
              <span class="fs-board-name"
                    contenteditable="false"
                    data-field="name"
                    data-board-id="<?= $b['board_id'] ?>"
                    ondblclick="this.contentEditable='true';this.focus();"
                    onblur="saveBoardInline(this)"
                    onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
              ><?= e($b['name']) ?></span>
              <span class="fs-board-desc"
                    contenteditable="false"
                    data-field="description"
                    data-board-id="<?= $b['board_id'] ?>"
                    ondblclick="this.contentEditable='true';this.focus();"
                    onblur="saveBoardInline(this)"
                    onkeydown="if(event.key==='Enter'){event.preventDefault();this.blur();}"
              ><?= e($b['description'] ?? '') ?></span>
              <span class="fs-board-stats">(<?= $b['thread_count'] ?>t / <?= $b['post_count'] ?>p)</span>
            </div>
            <div class="fs-board-actions">
              <button class="btn btn-danger btn-sm" onclick="deleteBoard(<?= $b['board_id'] ?>)">🗑️</button>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Quick add board -->
      <div class="fs-add-row">
        <input type="text" placeholder="New board name…" data-add-board-cat="<?= $cat['category_id'] ?>"
               onkeydown="if(event.key==='Enter')quickAddBoard(<?= $cat['category_id'] ?>,this);">
        <button class="btn btn-primary btn-sm"
                onclick="quickAddBoard(<?= $cat['category_id'] ?>, this.previousElementSibling)">+ Board</button>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="toast" id="toast"></div>

<script>
var CSRF = '<?= e($csrf) ?>';
var API  = '/api/forum_admin.php';

function showToast(msg, err){
  var t=document.getElementById('toast');
  t.textContent=msg;t.className='toast'+(err?' error':'');
  t.classList.add('is-visible');
  setTimeout(function(){t.classList.remove('is-visible');},2500);
}

function apiCall(data, cb){
  var fd=new FormData();
  fd.append('csrf_token',CSRF);
  for(var k in data) fd.append(k,data[k]);
  fetch(API,{method:'POST',body:fd})
    .then(function(r){return r.json();})
    .then(function(d){
      if(d.status==='success'){showToast(d.message||'Saved!');if(cb)cb(d);}
      else showToast(d.message||'Error',true);
    })
    .catch(function(){showToast('Network error',true);});
}

/* ===== INLINE EDITING ===== */
function saveCatInline(el){
  el.contentEditable='false';
  var catId=el.dataset.catId;
  var field=el.dataset.field;
  /* Need both name and desc */
  var header=el.closest('.fs-cat-header');
  var name=header.querySelector('[data-field="name"]').textContent.trim();
  var desc=header.querySelector('[data-field="description"]').textContent.trim();
  apiCall({action:'inline_edit_cat',category_id:catId,name:name,description:desc});
}

function saveBoardInline(el){
  el.contentEditable='false';
  var boardId=el.dataset.boardId;
  var row=el.closest('.fs-board');
  var name=row.querySelector('[data-field="name"]').textContent.trim();
  var desc=row.querySelector('[data-field="description"]').textContent.trim();
  apiCall({action:'inline_edit_board',board_id:boardId,name:name,description:desc});
}

/* ===== QUICK ADD ===== */
function quickAddCat(){
  var inp=document.getElementById('newCatName');
  var name=inp.value.trim();
  if(!name)return;
  apiCall({action:'quick_add_cat',name:name},function(){inp.value='';setTimeout(function(){location.reload();},600);});
}

function quickAddBoard(catId,inp){
  var name=inp.value.trim();
  if(!name)return;
  apiCall({action:'quick_add_board',category_id:catId,name:name},function(){inp.value='';setTimeout(function(){location.reload();},600);});
}

/* ===== DELETE ===== */
function deleteCat(id){
  if(!confirm('Delete this category?'))return;
  apiCall({action:'delete_cat',category_id:id},function(){
    var el=document.querySelector('[data-cat-id="'+id+'"].fs-cat');
    if(el)el.remove();
  });
}
function deleteBoard(id){
  if(!confirm('Delete this board?'))return;
  apiCall({action:'delete_board',board_id:id},function(){
    var el=document.querySelector('[data-board-id="'+id+'"]');
    if(el)el.remove();
  });
}

/* ===== DRAG & DROP — CATEGORIES ===== */
(function(){
  var container=document.getElementById('catContainer');
  var dragCat=null;

  container.addEventListener('dragstart',function(e){
    var cat=e.target.closest('.fs-cat');
    if(!cat)return;
    /* Check if we're dragging a board instead */
    var board=e.target.closest('.fs-board');
    if(board){handleBoardDragStart(e,board);return;}
    dragCat=cat;
    cat.style.opacity='.4';
    e.dataTransfer.effectAllowed='move';
    e.dataTransfer.setData('type','cat');
  });

  container.addEventListener('dragend',function(e){
    var cat=e.target.closest('.fs-cat');
    if(cat)cat.style.opacity='1';
    dragCat=null;
    document.querySelectorAll('.drag-over').forEach(function(el){el.classList.remove('drag-over');});
  });

  container.addEventListener('dragover',function(e){
    e.preventDefault();
    if(!dragCat)return;
    var target=e.target.closest('.fs-cat');
    if(!target||target===dragCat)return;
    document.querySelectorAll('.drag-over').forEach(function(el){el.classList.remove('drag-over');});
    target.classList.add('drag-over');
  });

  container.addEventListener('drop',function(e){
    e.preventDefault();
    if(!dragCat)return;
    var target=e.target.closest('.fs-cat');
    if(!target||target===dragCat)return;
    target.classList.remove('drag-over');
    /* Reorder DOM */
    var rect=target.getBoundingClientRect();
    var midY=rect.top+rect.height/2;
    if(e.clientY<midY){container.insertBefore(dragCat,target);}
    else{container.insertBefore(dragCat,target.nextSibling);}
    saveCatOrder();
  });

  function saveCatOrder(){
    var cats=container.querySelectorAll('.fs-cat');
    var order=[];
    cats.forEach(function(c,i){order.push(c.dataset.catId);});
    var fd=new FormData();
    fd.append('csrf_token',CSRF);
    fd.append('action','reorder_categories');
    order.forEach(function(id,i){fd.append('order['+i+']',id);});
    fetch(API,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
      if(d.status==='success')showToast('Order saved');else showToast('Error',true);
    });
  }

  /* ===== DRAG & DROP — BOARDS ===== */
  var dragBoard=null;

  window.handleBoardDragStart=function(e,board){
    dragBoard=board;
    board.style.opacity='.4';
    e.dataTransfer.effectAllowed='move';
    e.dataTransfer.setData('type','board');
    e.stopPropagation();
  };

  document.querySelectorAll('.fs-board').forEach(function(b){
    b.addEventListener('dragstart',function(e){handleBoardDragStart(e,b);});
    b.addEventListener('dragend',function(){b.style.opacity='1';dragBoard=null;
      document.querySelectorAll('.drag-over-board').forEach(function(el){el.classList.remove('drag-over-board');});
    });
  });

  document.querySelectorAll('.fs-boards-container').forEach(function(bc){
    bc.addEventListener('dragover',function(e){
      e.preventDefault();
      if(!dragBoard)return;
      e.stopPropagation();
      var target=e.target.closest('.fs-board');
      document.querySelectorAll('.drag-over-board').forEach(function(el){el.classList.remove('drag-over-board');});
      if(target&&target!==dragBoard)target.classList.add('drag-over-board');
    });

    bc.addEventListener('drop',function(e){
      e.preventDefault();
      e.stopPropagation();
      if(!dragBoard)return;
      document.querySelectorAll('.drag-over-board').forEach(function(el){el.classList.remove('drag-over-board');});
      var target=e.target.closest('.fs-board');
      if(target&&target!==dragBoard){
        var rect=target.getBoundingClientRect();
        if(e.clientY<rect.top+rect.height/2) bc.insertBefore(dragBoard,target);
        else bc.insertBefore(dragBoard,target.nextSibling);
      } else if(!target){
        /* Dropped on empty container */
        var empty=bc.querySelector('.fs-empty');
        if(empty)empty.remove();
        bc.appendChild(dragBoard);
      }
      saveBoardOrder();
    });
  });

  function saveBoardOrder(){
    var order=[];
    document.querySelectorAll('.fs-boards-container').forEach(function(bc){
      var catId=bc.dataset.catId;
      bc.querySelectorAll('.fs-board').forEach(function(b,i){
        order.push({id:b.dataset.boardId,cat:catId,sort:i});
      });
    });
    var fd=new FormData();
    fd.append('csrf_token',CSRF);
    fd.append('action','reorder_boards');
    fd.append('order',JSON.stringify(order));
    fetch(API,{method:'POST',body:fd}).then(function(r){return r.json();}).then(function(d){
      if(d.status==='success')showToast('Board order saved');else showToast('Error',true);
    });
  }
})();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>