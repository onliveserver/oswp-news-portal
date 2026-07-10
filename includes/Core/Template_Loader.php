<?php
namespace OSWP\Posts\Core;

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

class Template_Loader {
public function render( $template, array $context = [] ) {
$file = trailingslashit( OSWP_POSTS_PLUGIN_DIR . 'templates' ) . $template . '.php';
if ( ! file_exists( $file ) ) {
return '';
}
extract( $context, EXTR_SKIP );
ob_start();
include $file;
return ob_get_clean();
}

public function render_registration_fields( $fields, $old = [] ) {
foreach ( $fields as $field ) {
$id          = $field['id'];
$label       = $field['label'] ?? '';
$type        = $field['type'] ?? 'text';
$required    = ! empty( $field['required'] ) ? 'required' : '';
$width       = $field['width'] ?? '100';
$value       = $old[ $id ] ?? '';
$width_class = "oswp-form__group--" . $width;

if ( 'tab' === $type ) {
continue;
}

// Character limits
$char_limit_types = [ 'text', 'email', 'tel', 'url', 'password', 'textarea', 'wysiwyg' ];
$min_attr         = '';
$max_attr         = '';

if ( in_array( $type, $char_limit_types, true ) ) {
$min_len = isset( $field['min_limit'] ) ? absint( $field['min_limit'] ) : 0;
$max_len = ! empty( $field['max_limit'] ) ? absint( $field['max_limit'] ) : 0;

if ( $min_len > 0 ) {
$min_attr = ' minlength="' . $min_len . '"';
}
if ( $max_len > 0 ) {
$max_attr = ' maxlength="' . $max_len . '"';
}
}
echo '<div class="oswp-form__group ' . esc_attr( $width_class ) . '">';
echo '<label for="' . esc_attr( $id ) . '" class="oswp-form__label">';
echo esc_html( $label );
if ( $required ) {
echo '<span class="required">*</span>';
}
echo '</label>';

if ( 'textarea' === $type ) {
echo '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" class="oswp-form__textarea" rows="3" ' . $required . $min_attr . $max_attr . '>' . esc_textarea( $value ) . '</textarea>';
} elseif ( 'select' === $type ) {
echo '<select name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" class="oswp-form__select" ' . $required . '>';
echo '<option value="">' . sprintf( esc_html__( 'Select %s', 'oswp-posts' ), esc_html( $label ) ) . '</option>';
foreach ( $field['options'] ?? [] as $opt_val => $opt_label ) {
echo '<option value="' . esc_attr( $opt_val ) . '" ' . selected( $value, $opt_val, false ) . '>' . esc_html( $opt_label ) . '</option>';
}
echo '</select>';
} elseif ( 'checkbox' === $type ) {
echo '<label class="oswp-form__checkbox">';
echo '<input type="checkbox" name="' . esc_attr( $id ) . '" id="' . esc_attr( $id ) . '" value="1" ' . checked( $value, '1', false ) . ' ' . $required . ' />';
echo '<span class="oswp-form__checkbox-text">' . esc_html( $label ) . '</span>';
echo '</label>';
} elseif ( 'email' === $type ) {
echo '<input type="email" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" class="oswp-form__input" value="' . esc_attr( $value ) . '" ' . $required . $min_attr . $max_attr . ' />';
} elseif ( 'password' === $type ) {
echo '<input type="password" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" class="oswp-form__input" value="' . esc_attr( $value ) . '" ' . $required . $min_attr . $max_attr . ' />';
} else {
echo '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $id ) . '" class="oswp-form__input" value="' . esc_attr( $value ) . '" ' . $required . $min_attr . $max_attr . ' />';
}
echo '</div>';
}
}
}
