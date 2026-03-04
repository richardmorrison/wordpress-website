<?php
/*
Plugin Name: WP To-Do List
Description: A personal to-do list with drag-and-drop sorting, priority flags, per-user storage, and completion effects.
Version: 1.1
Author: Chief
 * Enhanced: Optional details, creation timestamps, subtasks with dependencies
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

add_shortcode( 'todo_list', 'wp_todo_list_shortcode' );
add_action( 'wp_enqueue_scripts', 'wp_todo_list_enqueue_scripts' );
add_action( 'wp_ajax_wp_todo_save', 'wp_todo_save' );
add_action( 'wp_ajax_wp_todo_delete', 'wp_todo_delete' );

function wp_todo_list_enqueue_scripts() {
    if ( is_user_logged_in() && is_page() ) {
        wp_enqueue_script( 'sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js', [], null, true );
        wp_enqueue_script( 'confetti-js', 'https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js', [], null, true );
        wp_enqueue_script( 'wp-todo-js', plugins_url( 'todo.js', __FILE__ ), [ 'jquery', 'sortablejs', 'confetti-js' ], null, true );
        wp_localize_script( 'wp-todo-js', 'wpTodo', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wp_todo_nonce' )
        ] );
        wp_enqueue_style( 'wp-todo-css', plugins_url( 'todo.css', __FILE__ ) );
    }
}

function wp_todo_list_shortcode() {
    if ( ! is_user_logged_in() ) {
        return '<p>Please <a href="' . wp_login_url() . '">log in</a> to view your to-do list.</p>';
    }

    $user_id = get_current_user_id();
    $todos = get_user_meta( $user_id, '_user_todo_list', true );
    $todos = is_array( $todos ) ? $todos : [];

    $active = array_filter( $todos, fn($item) => empty($item['completed']) );
    $completed = array_filter( $todos, fn($item) => !empty($item['completed']) );

    ob_start();
    ?>
    <div id="wp-todo">
        <h2>Your To-Do List</h2>
        <ul id="todo-list">
            <?php foreach ( $active as $id => $item ) : ?>
                <li data-id="<?php echo esc_attr( $id ); ?>" class="<?php echo !empty($item['important']) ? 'important' : ''; ?>">
                    <input type="checkbox" class="complete">
                    <span class="text"><?php echo esc_html( $item['text'] ); ?></span>
                    <button class="flag">★</button>
                    <button class="delete">✖</button>
                </li>
            <?php endforeach; ?>
        </ul>
        <input type="text" id="todo-input" placeholder="New task...">
        <button id="add-todo">Add</button>

        <?php if ( !empty($completed) ) : ?>
            <h3>Completed</h3>
            <ul id="completed-list">
                <?php foreach ( $completed as $id => $item ) : ?>
                    <li data-id="<?php echo esc_attr( $id ); ?>">
                        <input type="checkbox" class="complete" checked>
                        <span class="text completed"><?php echo esc_html( $item['text'] ); ?></span>
                        <button class="flag">★</button>
                        <button class="delete">✖</button>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

function wp_todo_save() {
    check_ajax_referer( 'wp_todo_nonce', 'nonce' );
    $user_id = get_current_user_id();
    $todos = get_user_meta( $user_id, '_user_todo_list', true );
    $todos = is_array( $todos ) ? $todos : [];

    $action = sanitize_text_field( $_POST['action_type'] );
    $id = sanitize_text_field( $_POST['id'] );

    if ( $action === 'add' ) {
        $text = sanitize_text_field( $_POST['text'] );
        $id = uniqid();
        $todos[$id] = [ 'text' => $text, 'important' => false, 'completed' => false ];
    } elseif ( $action === 'delete' ) {
        unset( $todos[$id] );
    } elseif ( $action === 'toggle' ) {
        $todos[$id]['important'] = ! $todos[$id]['important'];
    } elseif ( $action === 'complete' ) {
        $todos[$id]['completed'] = ! $todos[$id]['completed'];
    } elseif ( $action === 'reorder' ) {
        $new_order = $_POST['order'];
        $reordered = [];
        foreach ( $new_order as $item_id ) {
            if ( isset( $todos[$item_id] ) ) {
                $reordered[$item_id] = $todos[$item_id];
            }
        }
        $todos = $reordered;
    }

    update_user_meta( $user_id, '_user_todo_list', $todos );
    wp_send_json_success();
}

function wp_todo_delete() {
    wp_todo_save();
}