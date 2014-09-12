<?php

class RSS_Post_Aggregation_Feeds {

	/**
	 * Replaces wp_widget_rss_output
	 */
	function get_items( $rss_link, $args ) {

		$args = wp_parse_args( $args, array(
			'show_author'  => 0,
			'show_date'    => 0,
			'show_summary' => 0,
			'show_image'   => 0,
			'items'        => 0,
			'cache_time'   => DAY_IN_SECONDS
		) );
		$cache_time = (int) $args['cache_time'];

		$transient_id = md5( serialize( array_merge( array( 'rss_link'  => $rss_link ), $args ) ) );

		if ( ! isset( $_GET['delete-trans'] ) && $cache_time && $rss_items = get_transient( $transient_id ) ) {
			return $rss_items;
		}

		$items = (int) $args['items'];
		if ( $items < 1 || 20 < $items )
			$items = 10;
		$show_image    = (int) $args['show_image'];
		$show_summary  = (int) $args['show_summary'];
		$show_author   = (int) $args['show_author'];
		$show_date     = (int) $args['show_date'];


		$rss = fetch_feed( $rss_link );

		if ( is_wp_error( $rss ) ) {
			// if ( is_admin() || current_user_can( 'manage_options' ) )
			return array(
				'error' => sprintf( __( 'RSS Error: %s' ), $rss->get_error_message() ),
			);
		}


		if ( ! $rss->get_item_quantity() ) {
			$rss->__destruct();
			unset( $rss );
			return array(
				'error' => __( 'An error has occurred, which probably means the feed is down. Try again later.' ),
			);
		}

		$parse  = parse_url( $rss_link );
		$source = isset( $parse['host'] ) ? $parse['host'] : $rss_link;

		$rss_items = array();

		foreach ( $rss->get_items( 0, $items ) as $index => $item ) {
			$this->item = $item;

			$rss_item = array();

			$rss_item['link'] = $this->get_link();
			$rss_item['title'] = $this->get_title();

			if ( $show_image ) {
				$rss_item['image'] = $this->get_image();
			}

			if ( $show_summary ) {
				$rss_item['summary'] = $this->get_summary();
			}

			if ( $show_date ) {
				$rss_item['date'] = $this->get_date();
			}

			if ( $show_author ) {
				$rss_item['author'] = $this->get_author();
			}

			$rss_item['source']   = $source;
			$rss_item['rss_link'] = $rss_link;
			$rss_item['index']    = $index;

			$rss_items[ $index ]  = $rss_item;
		}

		$rss->__destruct();
		unset($rss);

		if ( $cache_time ) {
			set_transient( $transient_id, $rss_items, $cache_time );
		}

		return $rss_items;
	}

	public function get_title() {
		$title = esc_html( trim( strip_tags( $this->item->get_title() ) ) );
		if ( empty( $title ) ) {
			$title = __( 'Untitled' );
		}
		return $title;
	}

	public function get_link() {
		$link = $this->item->get_link();

		while ( stristr( $link, 'http' ) != $link ) {
			$link = substr( $link, 1 );
		}
		return esc_url( strip_tags( trim( $link ) ) );
	}

	public function get_date() {
		return ( $get_date = $this->item->get_date( 'U' ) )
			? date_i18n( get_option( 'date_format' ), $get_date )
			: '';
	}

	public function get_author() {
		return ( ( $author = $this->item->get_author() ) && is_object( $author ) )
			? esc_html( strip_tags( $author->get_name() ) )
			: '';
	}

	public function get_summary() {
		$summary = @html_entity_decode( $this->item->get_description(), ENT_QUOTES, get_option( 'blog_charset' ) );
		$summary = esc_attr( wp_trim_words( $summary, 100, ' [&hellip;]' ) );

		// Change existing [...] to [&hellip;].
		if ( '[...]' == substr( $summary, -5 ) ) {
			$summary = substr( $summary, 0, -5 ) . '[&hellip;]';
		}

		return esc_html( $summary );
	}

	public function get_image() {
		$content = @html_entity_decode( $this->item->get_content(), ENT_QUOTES, get_option( 'blog_charset' ) );

		@$this->dom()->loadHTML( $content );

		foreach ( $this->dom()->getElementsByTagName( 'img' ) as $img ) {
			if ( $src = $img->getAttribute('src') ) {
				return $src;
			}
		}
		return '';
	}

	public function dom() {
		if ( isset( $this->dom ) ) {
			return $this->dom;
		}
		$this->dom = new DOMDocument();

		return $this->dom;
	}

}
