jQuery(document).ready(function($) {
  const list = document.getElementById('todo-list');

  new Sortable(list, {
    animation: 150,
    onEnd: function() {
      const order = Array.from(list.children).map(li => li.dataset.id);
      save('reorder', { order });
    }
  });

  function save(action_type, extra = {}) {
    $.post(wpTodo.ajax_url, {
      action: 'wp_todo_save',
      nonce: wpTodo.nonce,
      action_type,
      ...extra
    });
  }

  $('#add-todo').on('click', function() {
    const text = $('#todo-input').val();
    if (!text) return;
    save('add', { text });
    location.reload();
  });

  $('#wp-todo').on('click', '.delete', function() {
    const li = $(this).closest('li');
    save('delete', { id: li.data('id') });
    li.remove();
  });

  $('#wp-todo').on('click', '.flag', function() {
    const li = $(this).closest('li');
    save('toggle', { id: li.data('id') });
    li.toggleClass('important');
  });

  $('#wp-todo').on('change', '.complete', function() {
    const li = $(this).closest('li');
    const id = li.data('id');
    save('complete', { id });
    confetti();
    setTimeout(() => location.reload(), 300);
  });
});
// TO-DO: Add nested sortable logic, disable parent complete until all children are done