# Abandoned

There is a better alternative: https://github.com/rubengc/cmb2-field-ajax-search

CMB2 User Search field
======================

Custom field for CMB2 which adds a user-search dialog for searching/attaching other user IDs with drag & drop support.

Adds a new text field type (with a button), `user_search_text` that adds a quick user search dialog for saving user IDs to a text input.

## Example

![16](https://cloud.githubusercontent.com/assets/403283/9018164/7f20de4a-37dc-11e5-994f-43fa17dba7ff.png)

### Initialize the fields

```php
// Classic CMB2 declaration
$cmb = new_cmb2_box( array(
	'id'           => 'prefix-metabox-id',
	'title'        => __( 'Post Info' ),
	'object_types' => array( 'post', ), // Post type
) );

// Add new field
$cmb->add_field( array(
    'name' => __( 'User(s)' ),
    'id' => 'prefix-user',
    'type' => 'user_search_text',
    'roles' => array( 'administrator', 'author', 'editor' )
) );
```

### Show in the frontend

```php
$users = get_post_meta( get_the_ID(), 'prefix-user', true );
$user_split = explode( ',', str_replace( ' ', '', $users ) );
foreach ( $user_split as $user ) {
	$user = get_user_by( 'id', $user );
	$name = trim( $user->display_name ) ? $user->display_name : $user->user_login;
	echo '<b>' . $name . '</b>, ';
}
```
