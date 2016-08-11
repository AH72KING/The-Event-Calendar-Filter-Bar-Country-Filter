<?php
/**
 * This file Path and name should be in src/tribe/filters/Country.php
 * Class Tribe__Events__Filterbar__Filters__Country
 */
class Tribe__Events__Filterbar__Filters__Country extends Tribe__Events__Filterbar__Filter {
	public $type = 'checkbox';

	public function get_admin_form() {
		$title = $this->get_title_field();
		$type = $this->get_multichoice_type_field();
		return $title.$type;
	}

	protected function get_values() {
		/** @var wpdb $wpdb */
		global $wpdb;

		// get venue IDs associated with published posts
		$venue_ids = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT m.meta_value FROM {$wpdb->postmeta} m INNER JOIN {$wpdb->posts} p ON p.ID=m.post_id WHERE p.post_type=%s AND p.post_status='publish' AND m.meta_key='_EventVenueID' AND m.meta_value > 0", Tribe__Events__Main::POSTTYPE ) );
		$venue_ids = array_filter( $venue_ids );
		if ( empty( $venue_ids ) ) {
			return array();
		}

		$venues = get_posts( array(
			'post_type' => Tribe__Events__Main::VENUE_POST_TYPE,
			'posts_per_page' => 200, // arbitrary limit
			'suppress_filters' => false,
			'post__in' => $venue_ids,
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'ASC',
		) );

		$venues_array = array();
		foreach ( $venues as $venue ) {
		//All the magic start here . this is code which will differ from venue filter
			$countryReturn ='';
			$countries = get_post_meta( $venue->ID,'_VenueCountry');
				foreach ( $countries as $country ):
						$countryReturn = $country;
				endforeach; 	
			$venues_array[] = array(
				'name' => $countryReturn,
				'value' => $venue->ID,
			);
		}

		return $venues_array;
	}

	protected function setup_join_clause() {
		global $wpdb;
		$this->joinClause = "LEFT JOIN {$wpdb->postmeta} AS venue_filter ON ({$wpdb->posts}.ID = venue_filter.post_id AND venue_filter.meta_key = '_EventVenueID')";
	}

	protected function setup_where_clause() {
		if ( is_array( $this->currentValue ) ) {
			$venue_ids = implode( ',', array_map( 'intval', $this->currentValue ) );
		}
		else {
			$venue_ids = intval( $this->currentValue );
		}

		$this->whereClause = " AND venue_filter.meta_value IN ($venue_ids) ";
	}
}
