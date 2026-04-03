<?php
/*
Plugin Name: eSIM Affiliate Tool
Plugin URI: https://blockstylist.com/
Description: A minimalist, high-performance eSIM catalog manager with editable plans and optional destination images.
Version: 1.4
Author: blockstylist
Author URI: https://blockstylist.com
License: GPLv2 or later
Text Domain: esim-affiliate-tool
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** 1. DATABASE SETUP **/
register_activation_hook( __FILE__, 'esim_aff_tool_setup_db' );
function esim_aff_tool_setup_db() {
	global $wpdb;
	$table_name      = $wpdb->prefix . 'eat_products';
	$charset_collate = $wpdb->get_charset_collate();
	$sql             = "CREATE TABLE $table_name (
        id int(11) NOT NULL AUTO_INCREMENT,
        destination varchar(100),
        link text, 
        image_link text,
        price varchar(50), 
        gb_info varchar(50), 
        validity_info varchar(50),
        PRIMARY KEY  (id)
    ) $charset_collate;";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
}

/** 2. FRONT-END ASSETS **/
add_action( 'wp_footer', 'esim_aff_tool_footer_assets' );
function esim_aff_tool_footer_assets() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}eat_products" );
	if ( ! $results ) {
		return;
	}

	$all_plans = array();
	$flag_map  = array();

	foreach ( $results as $row ) {
		$slug                = sanitize_title( $row->destination );
		$all_plans[ $slug ][] = array(
			'gb'    => esc_html( $row->gb_info ),
			'val'   => esc_html( $row->validity_info ),
			'price' => ( strpos( $row->price, '$' ) === false ? '$' : '' ) . esc_html( $row->price ),
			'link'  => esc_url( $row->link ),
		);
		if ( ! empty( $row->image_link ) ) {
			$flag_map[ $slug ] = esc_url( $row->image_link );
		}
	}
	?>
	<style>
		:root { --eat-dark: #121212; --eat-border: #e5e5ea; --eat-gray: #a1a1a6; }
		@keyframes eatFadeUp { from { opacity: 0; transform: scale(0.96) translateY(20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
		.eat-modal { display:none; position:fixed; z-index:99999; left:0; top:0; width:100%; height:100%; background: rgba(0,0,0,0.4); backdrop-filter: blur(10px); }
		.eat-modal-inner { background: #fff; margin: 8vh auto; width: 90%; max-width: 400px; border-radius: 24px; overflow: hidden; position: relative; font-family: -apple-system, BlinkMacSystemFont, sans-serif; box-shadow: 0 20px 40px rgba(0,0,0,0.15); animation: eatFadeUp 0.4s cubic-bezier(0.16, 1, 0.3, 1); }
		.eat-modal-scroll { padding: 20px; max-height: 50vh; overflow-y: auto; background: #f9f9fb; }
		.eat-close { position: absolute; right: 20px; top: 18px; font-size: 24px; color: #ccc; cursor: pointer; z-index: 10; transition: 0.3s; }
		.eat-close:hover { color: #000; transform: rotate(90deg); }
		.eat-header { display: flex; align-items: center; gap: 12px; padding: 30px 20px 15px; }
		.eat-flag { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 1px solid #eee; display: none; }
		.eat-flag[src^="http"] { display: block; }
		.eat-search-wrap { text-align: center; margin: 40px auto; max-width: 500px; padding: 0 20px; }
		.eat-dropdown { width: 100%; height: 50px; padding: 0 20px; border-radius: 50px; border: 1px solid var(--eat-border); font-size: 1rem; background: #fff; appearance: none; transition: 0.3s; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%238e8e93' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: calc(100% - 20px) center; }
		.eat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; margin-bottom: 15px; }
		.eat-item { display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; border: 1px solid var(--eat-border); border-radius: 14px; cursor: pointer; background: #fff; transition: 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
		.eat-item:hover { border-color: var(--eat-dark); transform: translateY(-3px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
		.eat-card { background: #fff; border-radius: 16px; padding: 14px 18px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; border: 1px solid #eee; }
		.eat-buy { background: var(--eat-dark); color: #fff !important; padding: 6px 14px; border-radius: 6px; font-weight: 700; text-decoration: none; font-size: 0.65rem; transition: 0.25s cubic-bezier(0.175, 0.885, 0.32, 1.275); text-transform: uppercase; letter-spacing: 0.05em; }
		.eat-buy:hover { transform: scale(1.05); opacity: 0.9; }
		.eat-credit { display: block; text-align: center; font-size: 9px; letter-spacing: 0.2em; text-transform: uppercase; color: var(--eat-gray); text-decoration: none; font-weight: 600; opacity: 0.6; transition: 0.3s; margin-top: 15px; }
		.eat-credit:hover { opacity: 1; color: var(--eat-dark); }
	</style>

	<div id="eatModal" class="eat-modal" onclick="this.style.display='none'">
		<div class="eat-modal-inner" onclick="event.stopPropagation()">
			<span class="eat-close" onclick="document.getElementById('eatModal').style.display='none'">×</span>
			<div class="eat-header">
				<img id="eat-m-flag" src="" class="eat-flag">
				<h3 id="eat-m-title" style="margin:0; font-weight: 800; font-size: 1.5rem; letter-spacing:-0.03em;"></h3>
			</div>
			<div id="eat-m-body" class="eat-modal-scroll"></div>
			<div style="text-align:center; padding: 15px; border-top:1px solid #f5f5f5;">
				<a href="https://blockstylist.com" target="_blank" class="eat-credit">powered by blockstylist</a>
			</div>
		</div>
	</div>

	<script>
	const eatData = <?php echo wp_json_encode( $all_plans ); ?>;
	const eatFlags = <?php echo wp_json_encode( $flag_map ); ?>;
	function openEsimModal(slug) {
		const plans = eatData[slug]; if(!plans) return;
		document.getElementById('eat-m-title').innerText = slug.replace(/-/g, ' ').toUpperCase();
		const flagImg = document.getElementById('eat-m-flag');
		if(eatFlags[slug]) { flagImg.src = eatFlags[slug]; flagImg.style.display = 'block'; } 
		else { flagImg.style.display = 'none'; flagImg.src = ''; }
		
		document.getElementById('eat-m-body').innerHTML = plans.map(p => `
			<div class="eat-card">
				<div style="line-height:1.2;">
					<div style="font-weight:800; font-size:1.1rem; letter-spacing:-0.02em;">${p.gb}</div>
					<div style="font-size:0.65rem; color:#999; font-weight:700; text-transform:uppercase;">${p.val}</div>
				</div>
				<div style="text-align:right;">
					<div style="font-weight:800; font-size:1rem; margin-bottom:4px;">${p.price}</div>
					<a href="${p.link}" target="_blank" class="eat-buy">Get →</a>
				</div>
			</div>`).join('');
		document.getElementById('eatModal').style.display = 'block';
	}
	</script>
	<?php
}

/** 3. SHORTCODES **/
add_shortcode( 'esim_search', function() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$dests = $wpdb->get_results( "SELECT DISTINCT destination FROM {$wpdb->prefix}eat_products ORDER BY destination ASC" );
	if ( ! $dests ) {
		return '';
	}
	ob_start(); ?>
	<div class="eat-search-wrap">
		<select class="eat-dropdown" onchange="openEsimModal(this.value); this.value='';">
			<option value="" disabled selected>Search country for eSIM...</option>
			<?php
			foreach ( $dests as $d ) :
				$s = sanitize_title( $d->destination );
				echo '<option value="' . esc_attr( $s ) . '">' . esc_html( $d->destination ) . '</option>';
			endforeach;
			?>
		</select>
		<a href="https://blockstylist.com" target="_blank" class="eat-credit">powered by blockstylist</a>
	</div>
	<?php
	return ob_get_clean();
} );

add_shortcode( 'esim_catalog', function() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$rows = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}eat_products" );
	if ( ! $rows ) {
		return '';
	}
	$unique = array();
	foreach ( $rows as $r ) {
		$slug  = sanitize_title( $r->destination );
		$price = (float) preg_replace( '/[^0-9.]/', '', $r->price );
		if ( ! isset( $unique[ $slug ] ) || $price < $unique[ $slug ]['min'] ) {
			$unique[ $slug ] = array(
				'name' => $r->destination,
				'min'  => $price,
				'img'  => $r->image_link,
				'slug' => $slug,
			);
		}
	}
	ksort( $unique );
	ob_start(); ?>
	<div class="eat-app">
		<div class="eat-grid">
			<?php foreach ( $unique as $d ) : ?>
				<div class="eat-item" onclick="openEsimModal('<?php echo esc_js( $d['slug'] ); ?>')">
					<div style="display:flex; align-items:center;">
						<?php if ( ! empty( $d['img'] ) ) : ?>
							<img src="<?php echo esc_url( $d['img'] ); ?>" style="width:20px; height:20px; border-radius:50%; margin-right:8px; object-fit:cover;">
						<?php endif; ?>
						<strong style="font-size:0.85rem; font-weight:700;"><?php echo esc_html( $d['name'] ); ?></strong>
					</div>
					<div style="text-align:right;">
						<span style="font-size:0.5rem; color:#999; display:block; font-weight:800;">FROM</span>
						<strong>$<?php echo number_format( $d['min'], 2 ); ?></strong>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<a href="https://blockstylist.com" target="_blank" class="eat-credit">powered by blockstylist</a>
	</div>
	<?php
	return ob_get_clean();
} );

/** 4. ADMIN DASHBOARD **/
add_action( 'admin_menu', function() {
	add_menu_page( 'eSIM Tool', 'eSIM Tool', 'manage_options', 'esim-affiliate', 'esim_aff_tool_admin_page', 'dashicons-admin-site-alt3', 26 );
} );

function esim_aff_tool_admin_page() {
	global $wpdb;

	if ( isset( $_POST['save_plan'] ) ) {
		check_admin_referer( 'eat_save' );
		$data = array(
			'destination'   => isset( $_POST['dest'] ) ? sanitize_text_field( wp_unslash( $_POST['dest'] ) ) : '',
			'link'          => isset( $_POST['link'] ) ? esc_url_raw( wp_unslash( $_POST['link'] ) ) : '',
			'image_link'    => isset( $_POST['img'] ) ? esc_url_raw( wp_unslash( $_POST['img'] ) ) : '',
			'price'         => isset( $_POST['price'] ) ? sanitize_text_field( wp_unslash( $_POST['price'] ) ) : '',
			'gb_info'       => isset( $_POST['gb'] ) ? sanitize_text_field( wp_unslash( $_POST['gb'] ) ) : '',
			'validity_info' => isset( $_POST['val'] ) ? sanitize_text_field( wp_unslash( $_POST['val'] ) ) : '',
		);
		if ( ! empty( $_POST['plan_id'] ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( "{$wpdb->prefix}eat_products", $data, array( 'id' => intval( $_POST['plan_id'] ) ) );
			echo '<div class="updated"><p>Plan updated.</p></div>';
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->insert( "{$wpdb->prefix}eat_products", $data );
			echo '<div class="updated"><p>Plan saved.</p></div>';
		}
	}

	if ( isset( $_GET['del'] ) ) {
		check_admin_referer( 'eat_del' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( "{$wpdb->prefix}eat_products", array( 'id' => intval( wp_unslash( $_GET['del'] ) ) ) );
	}

	$edit_plan = null;
	if ( isset( $_GET['edit'] ) ) {
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$edit_plan = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}eat_products WHERE id = %d", intval( wp_unslash( $_GET['edit'] ) ) ) );
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$products = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}eat_products ORDER BY destination ASC" );
	?>
	<div class="wrap">
		<h1>eSIM Affiliate Tool <small style="font-size:12px; font-weight:normal; opacity:0.6;">v1.4</small></h1>
		<div style="background:#fff; padding:20px; border-radius:12px; margin-bottom:20px; border:1px solid #ccd0d4; box-shadow: 0 2px 4px rgba(0,0,0,0.03);">
			<h3 style="margin-top:0;"><?php echo $edit_plan ? 'Edit eSIM Plan' : 'Add New eSIM Plan'; ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=esim-affiliate' ) ); ?>">
				<?php wp_nonce_field( 'eat_save' ); ?>
				<input type="hidden" name="plan_id" value="<?php echo $edit_plan ? esc_attr( $edit_plan->id ) : ''; ?>">
				<div style="display:flex; flex-wrap:wrap; gap:10px;">
					<input type="text" name="dest" required placeholder="Country Name" style="min-width:200px;" value="<?php echo $edit_plan ? esc_attr( $edit_plan->destination) : ''; ?>">
					<input type="text" name="price" placeholder="Price (e.g. 4.50)" value="<?php echo $edit_plan ? esc_attr( $edit_plan->price ) : ''; ?>">
					<input type="text" name="gb" placeholder="Data (e.g. 1GB)" value="<?php echo $edit_plan ? esc_attr( $edit_plan->gb_info ) : ''; ?>">
					<input type="text" name="val" placeholder="Validity (e.g. 7 Days)" value="<?php echo $edit_plan ? esc_attr( $edit_plan->validity_info ) : ''; ?>">
					<input type="url" name="link" required placeholder="Affiliate Link" style="flex:1;" value="<?php echo $edit_plan ? esc_attr( $edit_plan->link ) : ''; ?>">
					<input type="url" name="img" placeholder="Flag/Image URL (Optional)" style="flex:1;" value="<?php echo $edit_plan ? esc_attr( $edit_plan->image_link ) : ''; ?>">
					<input type="submit" name="save_plan" class="button button-primary" value="<?php echo $edit_plan ? 'Update Plan' : 'Save Plan'; ?>">
					<?php if ( $edit_plan ) : ?>
						<a href="?page=esim-affiliate" class="button">Cancel</a>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<table class="wp-list-table widefat fixed striped">
			<thead><tr><th>Destination</th><th>Details</th><th>Price</th><th>Actions</th></tr></thead>
			<tbody>
				<?php
				if ( $products ) :
					foreach ( $products as $p ) :
						?>
				<tr>
					<td><strong><?php echo esc_html( $p->destination ); ?></strong></td>
					<td><?php echo esc_html( $p->gb_info . ' / ' . $p->validity_info ); ?></td>
					<td>$<?php echo esc_html( $p->price ); ?></td>
					<td>
						<a href="?page=esim-affiliate&edit=<?php echo intval( $p->id ); ?>">Edit</a> | 
						<a href="<?php echo esc_url( wp_nonce_url( '?page=esim-affiliate&del=' . $p->id, 'eat_del' ) ); ?>" style="color:red;" onclick="return confirm('Delete this plan?');">Delete</a>
					</td>
				</tr>
						<?php
					endforeach;
				else :
					echo '<tr><td colspan="4">No plans found.</td></tr>';
				endif;
				?>
			</tbody>
		</table>
	</div>
	<?php
}
