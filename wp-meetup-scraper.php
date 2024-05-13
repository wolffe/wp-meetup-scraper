<?php
/**
 * Plugin Name: WP Meetup Scraper
 * Plugin URI: https://getbutterfly.com/
 * Description: A Meetup scraper plugin for WordPress.
 * Version: 1.0.1
 * Author: Ciprian Popescu
 * Author URI: https://getbutterfly.com/
 * License: GNU General Public License v3 or later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

function itn_get_meetup_events( $url ) {
    // Generate a unique transient key based on the URL
    $transient_key = 'meetup_events_' . md5( $url );

    // Check if the transient exists
    $events = get_transient( $transient_key );

    // If transient doesn't exist or is expired, fetch new data
    if ( ! $events ) {
        // Initialize cURL session
        $ch = curl_init();

        // Set cURL options
        curl_setopt( $ch, CURLOPT_URL, $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_HEADER, false );
        curl_setopt( $ch, CURLOPT_ENCODING, 'gzip,deflate' );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, [ 'Connection: keep-alive' ] );

        // Execute cURL session and get the HTML content
        $html = curl_exec( $ch );

        // Check for cURL errors
        if ( curl_errno( $ch ) ) {
            echo 'Curl error: ' . curl_error( $ch );
            exit;
        }

        // Close cURL session
        curl_close( $ch );

        // Create a DOMDocument object to parse the HTML
        $dom = new DOMDocument;
        libxml_use_internal_errors( true ); // Disable libxml errors

        // Load HTML content into the DOMDocument
        $dom->loadHTML( $html );

        // Use DOMXPath to query the document
        $xpath = new DOMXPath( $dom );

        // Specify the XPath queries to select date, title, location and link
        $date_nodes     = $xpath->query( '//time[@class=""]/@datetime' );
        $title_nodes    = $xpath->query( '//h2[@class="text-gray7 font-medium text-base pb-1 pt-0 line-clamp-3"]' );
        $location_nodes = $xpath->query( '//p[@class="text-gray6"][contains(., "Group name:")]/following-sibling::p[1]' );
        $link_nodes     = $xpath->query( '//a[@data-event-label="Event card"]/@href' );

        // Prepare an array to store event data
        $events = [];

        // Check if nodes were found
        if ( $date_nodes->length == 0 || $title_nodes->length == 0 || $location_nodes->length == 0 || $link_nodes->length == 0 ) {
            // Handle case when nodes are not found
            // Log or display a message
            // No events found
        } else {
            // Output the last 10 date, title, and link
            $total_events = min( 10, $date_nodes->length ); // Get the minimum of 10 and the total number of events

            for ( $i = $date_nodes->length - 1; $i >= $date_nodes->length - $total_events; $i-- ) {
                $date     = $date_nodes->item( $i )->nodeValue;
                $title    = $title_nodes->item( $i )->nodeValue;
                $location = $location_nodes->item( $i )->nodeValue;
                $link     = $link_nodes->item( $i )->nodeValue;

                // Add event data to the array
                $events[] = [
                    'date'     => $date,
                    'title'    => $title,
                    'location' => $location,
                    'link'     => $link,
                ];
            }

            // Remove 'Z[UTC]' from the date strings
            foreach ( $events as &$event ) {
                $event['date'] = str_replace( 'Z[UTC]', '', $event['date'] );
            }

            // Sort events based on date (ascending order)
            usort(
                $events,
                function( $a, $b ) {
                    $date_time_a = new DateTime( $a['date'] );
                    $date_time_b = new DateTime( $b['date'] );

                    return $date_time_a <=> $date_time_b;
                }
            );
        }

        // Set the transient with a 24-hour expiration
        set_transient( $transient_key, $events, 24 * HOUR_IN_SECONDS );
    }

    return $events;
}



function itn_get_events( $url ) {
    $out = '';

    // Check if array is empty
    if ( empty( $url ) ) {
        $out .= '<p>There are no events results that match these filters.</p>';
    }

    foreach ( $url as $event ) {
        $date_string = str_replace( [ 'Z[UTC]' ], '', $event['date'] );
        $date_time   = new DateTime( $date_string, new DateTimeZone( 'UTC' ) );

        $date_time->setTimezone( new DateTimeZone( 'Europe/Dublin' ) );
        $formatted_date = $date_time->format( 'j F Y, H:i' );

        // {$event['location']} does not work
        $out .= '<h3><a href="' . $event['link'] . '">' . $event['title'] . '</a></h3>';
        $out .= "<p class='itn--meta'>On {$formatted_date}</p>";
        $out .= '<hr>';
    }

    return $out;
}



function itn_meetup_events_shortcode( $atts ) {
    // Get URL from shortcode attributes
    $attributes = shortcode_atts(
        [
            'url'  => '',
            'type' => '',
        ],
        $atts
    );

    $out = '<style>
    .itn-grid-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        grid-gap: 4em;
        margin-bottom: 4em;
    }
    .itn-grid-container.itn-grid-3-column {
        grid-template-columns: repeat(3, 1fr);
    }

    @media only screen and (max-width: 768px) {
        .itn-grid-container,
        .itn-grid-container.itn-grid-3-column {
            grid-template-columns: repeat(1, 1fr);
        }
    }

    hr {
        border: 0 none;
        border-top: 1px solid rgba(255, 255, 255, 0.25);
    }
    .itn--meta {
        color: #747d8c;
        font-size: 15px;
    </style>';

    $meetup_events_url1 = itn_get_meetup_events( 'https://www.meetup.com/find/ie--dublin/technology/' );
    $meetup_events_url2 = itn_get_meetup_events( 'https://www.meetup.com/find/?source=EVENTS&categoryId=546&location=ie--Dublin&distance=tenMiles' );
    $meetup_events_url3 = itn_get_meetup_events( 'https://www.meetup.com/find/?source=EVENTS&categoryId=546&location=ie--Cork&distance=tenMiles' );
    $meetup_events_url4 = itn_get_meetup_events( 'https://www.meetup.com/find/?source=EVENTS&categoryId=546&location=ie--Galway&distance=tenMiles' );
    $meetup_events_url5 = itn_get_meetup_events( 'https://www.meetup.com/find/?source=EVENTS&categoryId=546&location=ie--Limerick&distance=tenMiles' );

    $out .= '<div class="itn-grid-container">
        <div class="itn-grid-item">
            <h2>Technology events in Ireland</h2>';

            $out .= itn_get_events( $meetup_events_url1 );

        $out .= '</div>
        <div class="itn-grid-item">
            <h2>All events in Dublin</h2>';

            $out .= itn_get_events( $meetup_events_url2 );

        $out .= '</div>
    </div>

    <div class="itn-grid-container itn-grid-3-column">
        <div class="itn-grid-item">
            <h2>All events in Cork</h2>';

            $out .= itn_get_events( $meetup_events_url3 );

        $out .= '</div>
        <div class="itn-grid-item">
            <h2>All events in Galway</h2>';

            $out .= itn_get_events( $meetup_events_url4 );

        $out .= '</div>
        <div class="itn-grid-item">
            <h2>All events in Limerick</h2>';

            $out .= itn_get_events( $meetup_events_url5 );

        $out .= '</div>
    </div>';

    return $out;
}

add_shortcode( 'meetup-events', 'itn_meetup_events_shortcode' );
