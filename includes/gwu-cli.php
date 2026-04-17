<?php                                                                                                                                                        
  if ( ! defined( 'ABSPATH' ) ) exit;         
                                                                                                                                                               
  if ( defined( 'WP_CLI' ) && WP_CLI ) {
                                                                                                                                                               
      class GWU_CLI_Command {
                                                                                                                                                               
          /**     
           * Import gift wraps from CSV
           *
           * ## OPTIONS                       
           *                              
           * --csv=<file>
           * : Path to CSV file                                                                                                                                
           *
           * [--dry-run]                                                                                                                                       
           * : Run without inserting data
           *
           * ## EXAMPLES
           *
           *     wp gift-wrap import --csv=wraps.csv
           *     wp gift-wrap import --csv=wraps.csv --dry-run
           */                             
          public function import( $args, $assoc_args ) {
                                                                                                                                                               
              $file = $assoc_args['csv'] ?? '';
                                                                                                                                                               
              if ( empty( $file ) ) {
                  WP_CLI::error( __( 'CSV file is required. Usage: --csv=path/to/file.csv', 'gift-wrap' ) );                                                   
              }                               
                                                                                                                                                               
              if ( ! file_exists( $file ) || ! is_readable( $file ) ) {
                  WP_CLI::error( sprintf( __( 'Cannot read file: %s', 'gift-wrap' ), $file ) );                                                                
              }
                                                                                                                                                               
              $dry_run = isset( $assoc_args['dry-run'] );
                                                                                                                                                               
              if ( $dry_run ) {
                  WP_CLI::log( '--- Dry run mode ---' );                                                                                                       
              }
                                                                                                                                                               
              $handle = fopen( $file, 'r' );

              if ( ! $handle ) {              
                  WP_CLI::error( __( 'Unable to open CSV file.', 'gift-wrap' ) );
              }
                                                                                                                                                               
              $header = fgetcsv( $handle ); 
              
              // Strip BOM (byte order mark) that Excel/Numbers add                                                                                                                        
              $header[0] = preg_replace( '/^\x{FEFF}/u', '', $header[0] );

                                                                                                                                    
            // Trim whitespace and lowercase all header values                                                                                                                           
                $header = array_map( function ( $col ) {                                                                                                                                     
                    return strtolower( trim( $col ) );                                                                                                                                       
                }, $header ); 
                  
                $header = array_values( array_filter( $header, function ( $col ) {                                                                                                           
                    return $col !== '';                 
                } ) );
              
              $expected = [ 'title', 'surcharge', 'is_active', 'expiry_date' ];                                                                                
                  
              if ( $header !== $expected ) {                                                                                                                   
                fclose( $handle );
                WP_CLI::error(
                    sprintf( 'Invalid header. Expected: %s | Got: %s',                                                                                                                   
                            implode( ',', $expected ),      
                            implode( ',', $header )     
                    )
                );                        
              }                           
                                                                                                                                                               
              $rows = [];                                                                                                                                      
              while ( ( $row = fgetcsv( $handle ) ) !== false ) {                                                                                              
                  $rows[] = $row;                                                                                                                              
              }   
              fclose( $handle );

              if ( empty( $rows ) ) {         
                  WP_CLI::warning( __( 'CSV has no data rows.', 'gift-wrap' ) );
                  return;
              }                                                                                                                                                
              WP_CLI::log(
                    sprintf(
                        __( 'Total rows to process: %d', 'gift-wrap' ),
                        count( $rows )
                    )
            );

              $imported = 0;                                                                                                                                   
              $skipped  = 0;
              $errors   = [];                 
                                          
              $progress = \WP_CLI\Utils\make_progress_bar(
                  'Importing wraps',                                                                                                                           
                  count( $rows )
              );                                                                                                                                               
                  
              foreach ( $rows as $index => $row ) {
                                          
                  $row_num = $index + 1;
                                                                                                                                                               
                  if ( count( $row ) < 4 ) {
                      $errors[] = "Row {$row_num}: not enough columns";                                                                                        
                      $progress->tick();      
                      continue;           
                  }
                                                                                                                                                               
                  //list( $title, $price, $active, $expiry ) = $row;
                  $title  = $row[0] ?? '';
                  $price  = $row[1] ?? '';
                  $active = $row[2] ?? '';
                  $expiry = $row[3] ?? '';
                                                                                                                                                               
                  $title  = sanitize_text_field( $title );
                  $price  = is_numeric( $price ) ? max( 0, (float) $price ) : null;
                  $active = in_array( $active, [ '1', 1, true ], true ) ? 1 : 0;
                  $expiry = sanitize_text_field( $expiry );                                                                                                    
                                              
                  if ( empty( $title ) || $price === null ) {                                                                                                  
                      $errors[] = sprintf(
                            __( 'Row %d: invalid title or price', 'gift-wrap' ),
                            $row_num
                        );
                      $progress->tick();                                                                                                                       
                      continue;               
                  }                                                                                                                                            
                  
                  if ( $expiry !== '' ) {                                                                                                                      
                      $dt = DateTime::createFromFormat( 'Y-m-d', $expiry );
                      if ( ! $dt || $dt->format( 'Y-m-d' ) !== $expiry ) {                                                                                     
                          $errors[] = "Row {$row_num}: invalid date '{$expiry}', skipping date";
                          $expiry = '';       
                      }                   
                  }                                                                                                                                            
                                                                                                                                                               
                  // Exact title match to avoid duplicates                                                                                                     
                    $existing = get_posts([
                            'post_type'      => 'gift_wrap_option',
                            's'              => $title,
                            'post_status'    => 'any',
                            'posts_per_page' => 5,
                    ]);

                    $duplicate = false;

                    foreach ( $existing as $post ) {
                        if ( get_the_title( $post ) === $title ) {

                            $existing_price  = (float) get_post_meta( $post->ID, 'surcharge', true );
                            $existing_expiry = (string) get_post_meta( $post->ID, 'expiry_date', true );

                            if ( $existing_price === $price && $existing_expiry === $expiry ) {
                                $duplicate = true;
                                break;
                            }
                        }
                    }

                    if ( $duplicate ) {
                        $skipped++;
                        $progress->tick();
                        continue;
                    }                           
                                          
                  if ( $dry_run ) {
                      WP_CLI::log( "  Would import: {$title} (\${$price})" );                                                                                            
                      $progress->tick();                                                                                                                       
                      continue;
                  }                                                                                                                                            
   
                  $post_id = wp_insert_post([                                                                                                                  
                      'post_type'   => 'gift_wrap_option',
                      'post_title'  => $title,
                      'post_status' => $active ? 'publish' : 'draft'
                  ]);
                                                                                                                                                               
                  if ( is_wp_error( $post_id ) ) {
                      $errors[] = "Row {$row_num}: insert failed for '{$title}'";                                                                              
                      $progress->tick();      
                      continue;           
                  }
                                                                                                                                                               
                  update_post_meta( $post_id, 'surcharge', $price );
                  update_post_meta( $post_id, 'is_active', $active );                                                                                          
                  update_post_meta( $post_id, 'expiry_date', $expiry );
                                                                                                                                                               
                  $imported++;
                  $progress->tick();                                                                                                                           
              }   

              $progress->finish();            
                                          
              WP_CLI::success(
                  sprintf( 'Done. Imported: %d, Skipped: %d, Errors: %d', $imported, $skipped, count( $errors ) )                                              
              );
                                                                                                                                                               
              foreach ( $errors as $error ) { 
                  //WP_CLI::warning( $error );
                  WP_CLI::warning( 'Errors encountered:' );
                  foreach ( $errors as $error ) {
                        WP_CLI::log( ' - ' . $error );
                   }
              }
          }                                                                                                                                                    
      }
                                                                                                                                                               
      WP_CLI::add_command( 'gift-wrap', 'GWU_CLI_Command' );
  }