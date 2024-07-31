<?php
$q23_drawing_security_salt = ' PUT A SECRET HERE! IMPORTANT! ';
$q23_drawing_epoch_max_age = 60 * 60 * 24; // 24 hours to draw
$color_sets = [
   [ '#00007f', '#7f7fff' ],
   [ '#001524', '#15616d' ],
   [ '#0075c4', '#f05783' ],
   [ '#007f00', '#7fff7f' ],
   [ '#023047', '#219ebc' ],
   [ '#03045e', '#023e8a', '#0077b6', '#0096c7', '#00b4d8' ],
   [ '#0d3b66', '#f4d35e' ],
   [ '#176ab2', '#219bff' ],
   [ '#191919', '#007ea7', '#9ad1d4' ],
   [ '#283618', '#563b93' ],
   [ '#31263e', '#eca72c' ],
   [ '#3772ff', '#df2935', '#fdca40' ],
   [ '#3a5553', '#33a099' ],
   [ '#792e4a', '#588157' ],
   [ '#3c096c', '#5a189a', '#7b2cbf', '#9d4edd' ],
   [ '#3c7e14', '#955438', '#1727f8' ],
   [ '#114ead', '#49ce14' ],
   [ '#534b63', '#9889b5' ],
   [ '#5e503f', '#c6ac8f' ],
   [ '#5f0f40', '#9a031e' ],
   [ '#444444', '#898989' ],
   [ '#72452c', '#9e5f3d' ],
   [ '#26350d', '#9db54a' ],
   [ '#7678ed', '#f7b801' ],
   [ '#78290f', '#ff7d00' ],
   [ '#7f0000', '#ff7f7f' ],
   [ '#4a0e49', '#960082' ],
   [ '#7f3300', '#ffb27f' ],
   [ '#067587', '#b70934' ],
   [ '#a4133c', '#ff4d6d' ],
   [ '#a5be00', '#679436' ],
   [ '#d60270', '#9b4f96', '#0038a8' ],
   [ '#e40303', '#ff8c00', '#ffed00', '#008026', '#004cff', '#732982' ],
   [ '#f15bb5', '#9b5de5' ],
   [ '#fb8500', '#ffb703' ],
   [ '#ff595e', '#8ac926', '#1982c4' ],
   [ '#ff6d00', '#240046' ],
   [ '#ff74a6', '#ffc8dd' ],
];

// Convenient debug function to preview the color sets so I can tell how broken they are
// function q23_comment_drawing_show_color_sets_to_admin(){
//   global $color_sets;
//   if( ! $_GET['_q23_show_comment_drawing_color_sets'] ) return;
//   echo '<h1>Color Sets</h1>';
//   echo '<ol>';
//   foreach($color_sets as $color_set){
//     echo '<li style="display: flex;">';
//     foreach($color_set as $color){
//       echo "<div style=\"background-color: ${color}; color: white; padding: 0.5em; width: 50px; height: 50px; border: 1px solid #ccc;\">${color}</div> ";
//     }
//     echo '</li>';
//   }
//   echo '</ol>';
//   die();
// }
// add_action( 'template_redirect', 'q23_comment_drawing_show_color_sets_to_admin' );

function q23_comment_drawings_allowed_on_post( $post_id ) {
  $post_id = intval( $post_id );
  $draw_comments = get_post_meta( $post_id, 'draw_comments', true );
  return ( empty( $draw_comments ) ? false : $draw_comments );
}

function q23_comment_drawing_get_sig( $post_id, $colors, $epoch ) {
  global $q23_drawing_security_salt;
  return hash( 'sha256', implode( ':', [ $q23_drawing_security_salt, $post_id, $colors, $epoch ] ) );
}

function q23_comment_drawing_check_sig( $header ) {
  global $q23_drawing_security_salt, $q23_drawing_epoch_max_age;
  list( $hstart, $post_id, $colors, $epoch, $sig ) = explode( ':', $header );
  if( $q23_drawing_epoch_max_age < date( 'U' ) - intval( $epoch ) ) return false; // signature must be recent enough
  if( 'future' !== q23_comment_drawings_allowed_on_post( $post_id ) ) return false; // post specified in signature must be eligible for drawings
  return $sig === q23_comment_drawing_get_sig( $post_id, $colors, $epoch );
}

function q23_maybe_enable_comment_drawing( $scripts ) {
  global $post;
  if( ! is_single() ) return $scripts; // only on single pages
  if( ( ! $post ) || 'future' !== q23_comment_drawings_allowed_on_post( $post->ID ) ) return $scripts;
  $scripts['comment_drawing'] = file_get_contents(get_template_directory() . '/inc/q23-draw-comments.js');
  return $scripts;
}
add_filter( 'q23_footer_js', 'q23_maybe_enable_comment_drawing' ); // NOTE: q23_footer_js is a custom hook I use for injecting JS; you'll need your own!

function q23_maybe_animate_existing_drawings( $scripts ) {
  global $post;
  if( ! is_single() ) return $scripts; // only on single pages
  if( ( ! $post ) || ! q23_comment_drawings_allowed_on_post( $post->ID ) ) return $scripts;
  $scripts['comment_animate_drawing'] = file_get_contents(get_template_directory() . '/inc/q23-draw-comments-animate.js');
  return $scripts;
}
add_filter( 'q23_footer_js', 'q23_maybe_animate_existing_drawings' ); // NOTE: q23_footer_js is a custom hook I use for injecting JS; you'll need your own!

function q23_comment_drawing_get_palette( $request ){
  global $color_sets;
  $post_id = intval( $request[ 'post_id' ] );
  if( 'future' !== q23_comment_drawings_allowed_on_post( $post_id ) ) {
    return new WP_Error( 'invalid_post_id', 'Invalid post ID ' . $post_id, array( 'status' => 404 ) );
  }
  $color_index = $post_id + get_comment_count( $post_id )['all'] + ( date( 'U' ) / 1200 ); // index shifts per post, per commentcount, or every 20 mins
  $colors = $color_sets[ $color_index % count( $color_sets ) ];
  $epoch = date( 'U' );
  $sig = q23_comment_drawing_get_sig( $post_id, implode( '', $colors ), $epoch );
  return rest_ensure_response( [
    'palette' => $colors,
    'epoch' => $epoch,
    'sig' => $sig,
  ] );
}

function q23_comment_drawing_register_get_palette() {
  register_rest_route( 'q23/v1', '/palette', [
    'methods'  => WP_REST_Server::READABLE,
    'callback' => 'q23_comment_drawing_get_palette',
  ] );
}
add_action( 'rest_api_init', 'q23_comment_drawing_register_get_palette' );

/**
 * Handle approvals of comment drawings.
 */
function q23_pre_comment_approved_drawings($approved, $commentdata) {
  if( $commentdata['comment_type'] !== 'comment' ) return $approved; // don't wrangle non-comment comments here
  if( ! str_starts_with( $commentdata['comment_content'], 'Q23DRW:' ) ) return $approved; // don't wrangle non-drawing comments here
  if( 'future' !== q23_comment_drawings_allowed_on_post( $commentdata['comment_post_ID'] ) ) return 'spam'; // "drawing" comments on non-drawing posts are spam
  $header = explode( "\n", $commentdata['comment_content'] )[0];
  list( $hstart, $post_id, $colors, $epoch, $sig ) = explode( ':', $header );
  if( intval( $post_id ) !== intval( $commentdata['comment_post_ID'] ) ) return 'spam'; // always spam comments that claim to be for different posts
  if( ! q23_comment_drawing_check_sig( $header ) ) return 'spam'; // always spam invalid signatures
  if( strlen( $commentdata['comment_content'] ) < 100 ) return 0; // send to 'pending' any drawing comments that seem very short
  return $approved; // anything that gets this far retains its existing status
}
add_filter('pre_comment_approved', 'q23_pre_comment_approved_drawings', 150, 2);

function q23_drawing_comment_svg_path( $current_path, $color, $anim_offset ) {
  $current_path = trim( $current_path );
  //return "<path d=\"${current_path}\" fill=\"none\" stroke-width=\"6\" stroke=\"${color}\" opacity=\"0\"><animate attributename=\"opacity\" from=\"0\" to=\"1\" dur=\"0.05s\" begin=\"" . $anim_offset . "s\" fill=\"freeze\"></animate></path>\n";
  return "<path d=\"${current_path}\" fill=\"none\" stroke-width=\"6\" stroke=\"${color}\" style=\"transition-delay: ${anim_offset}s;\" />\n";
}

function q23_display_drawing_comment_to_svg( $comment_text ){
  $lines = explode( "\n", $comment_text );
  $header = $lines[0];
  list( $hstart, $post_id, $colors, $epoch, $sig ) = explode( ':', $header );
  $palette = str_split( $colors, 7 );
  $body = implode( ' ', array_slice( $lines, 1 ) );
  $commands = explode( ' ', $body );
  $svg_content = '';
  $color = $palette[0];
  $debug = [];
  $current_path = '';
  $anim_offset = 0;
  $anim_block_length = 0;
  $max_anim_block_length = 4;
  foreach ($commands as $command) {
    if( $anim_block_length >= $max_anim_block_length ) { // tie-off this block and move on to the next, for smoother animation!
      $svg_content .= q23_drawing_comment_svg_path( $current_path, $color, $anim_offset );
      $anim_offset += 0.1;
      $final_coords = array_slice( explode( ' ', trim( $current_path ) ), -2 );
      $current_path = ( count( $final_coords ) == 2 ) ? 'M ' . $final_coords[0] . ' ' . $final_coords[1] : ' ';
      $anim_block_length = 0;
      $max_anim_block_length += 0.25; // every whole number this goes up makes subsequent blocks faster, to gradually accelerate LOOONG drawings
    }
    $keyword = substr( $command, 0, 1);
    $args = substr( $command, 1 );
    $debug[] = "${keyword}-${args}";
    switch ( $keyword ) {
      case 'P':
        // end current path
        if($current_path !== '') {
          $svg_content .= q23_drawing_comment_svg_path( $current_path, $color, $anim_offset );
          $anim_offset += 0.05;
          $current_path = '';
          $anim_block_length = 0;
        }
        // change color
        $color = $palette[intval($args)];
        $debug[] = "Color changed to ${color}";
        break;

      case 'C':
        // end current path
        if($current_path !== '') {
          $svg_content .= q23_drawing_comment_svg_path( $current_path, $color, $anim_offset );
          $anim_offset += 0.05;
          $current_path = '';
          $anim_block_length = 0;
        }
        // draw a dot
        $arg_parts = array_map( 'floatval', explode( ',', $args ) );
        // $svg_content .= "<circle cx=\"" . $arg_parts[0] . "\" cy=\"" . $arg_parts[1] . "\" r=\"4\" fill=\"${color}\" opacity=\"0\"><animate attributename=\"opacity\" from=\"0\" to=\"1\" dur=\"0.025s\" begin=\"" . $anim_offset . "s\" fill=\"freeze\"></animate></circle>\n";
        $svg_content .= "<circle cx=\"" . $arg_parts[0] . "\" cy=\"" . $arg_parts[1] . "\" r=\"4\" fill=\"${color}\" style=\"transition-delay: ${anim_offset}s;\" />\n";
        $anim_offset += 0.025;
        $debug[] = "Circle at ${args}";
        break;

      case 'M':
      case 'L':
        // add to current path
        $formatted_args = implode( ' ', array_map( 'floatval', explode( ',', $args ) ) );
        $current_path .= "${keyword} ${formatted_args} ";
        $anim_block_length++;
        $debug[] = "${keyword} to ${formatted_args}";
        break;
    }
  }
  // complete final path
  if($current_path !== '') {
    $svg_content .= q23_drawing_comment_svg_path( $current_path, $color, $anim_offset );
  }
  // output:
  return "<svg class=\"q23-slow-svg\" viewBox=\"0 0 980 500\" xmlns=\"http://www.w3.org/2000/svg\" style=\"width: 100%; aspect-ratio: 980 / 500;\">${svg_content}</svg>";
}

/**
 * Handle display of drawing comments.
 */
function q23_display_drawing_comment( $comment_text, $comment = null ) {
  if( null === $comment ) return $comment_text; // safety: shortcuts the callback that occurs when WRITING to the db
  if( ! q23_comment_drawings_allowed_on_post( $comment->comment_post_ID ) ) return $comment_text; // only display drawings on posts that allow them
  if( ! str_starts_with( $comment->comment_content, 'Q23DRW:' ) ) return $comment_text;
  return q23_display_drawing_comment_to_svg( $comment_text );
}
add_filter( 'comment_text', 'q23_display_drawing_comment', 10, 2 );
