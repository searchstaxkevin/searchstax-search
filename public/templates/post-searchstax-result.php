<?php

/*
 *
 * @link       https://www.searchstax.com
 * @since      1.0.0
 *
 * @package    Searchstax_Search
 * @subpackage Searchstax_Search/public/partials
 * 
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( '-1' );
}

get_header();

$post = $GLOBALS['post'];
$meta = get_post_meta($post->ID);

$show_search_bar = get_post_meta($post->ID, 'search_bar', true);
$fixed_search_query = get_post_meta($post->ID, 'fixed_search_query', true);

$token = get_option('searchstax_search_token_read');
$select_api = get_option('searchstax_search_api_select');

$search_profile = get_post_meta($post->ID, 'search_profile', true);

if ( $meta['search_config'][0] == 'config_static' ) {

	$selected_post_types = get_post_meta($post->ID, 'search_result_post_types', true);
	$selected_categories = get_post_meta($post->ID, 'search_result_post_categories', true);
	$selected_tags = get_post_meta($post->ID, 'search_result_post_tags', true);

	$query = '';
	if ( isset($_GET['searchstax[query]']) ) {
		$query = $_GET['searchstax[query]'];
	}
	$start = 0;
	if ( isset($_GET['searchStart']) ) {
		$start = $_GET['searchStart'];
	}
	if ( isset($_GET['post_type']) ) {
		$selected_post_types = [$_GET['post_type']];
	}
	if ( isset($_GET['category']) ) {
		$selected_categories = [$_GET['category']];
	}
	if ( isset($_GET['tag']) ) {
		$selected_tags = [$_GET['tag']];
	}

	?>
	<div class="searchstax_search_container">
		<?php
			if( $show_search_bar != 'fixed_search' ) {
			?>
				<div>
					<form action="">
						<div class="searchstax_search_search_bar">
							<input class="searchstax_search_search_input" type="text" name="searchstax[query]" value="<?php echo $query; ?>" autocomplete="off" />
							<input class="searchstax_search_search_submit" type="submit" value="Search" />
						</div>
					</form>
				</div>
			<?php 
			}
			else {
				$query = $fixed_search_query;
			}
			if ( $query != '' && $token != '' && $select_api != '' ) {

				$url = $select_api . '?q=(body:*' . $query . '* OR title:*' . $query . '*)';
				if ( count($selected_post_types) > 0 ) {
					$url .= '&fq=post_type:("' . join('" OR "', $selected_post_types) . '")';
				}
				if ( count($selected_categories) > 0 ) {
					$url .= '&fq=categories:("' . join('" OR "', $selected_categories) . '")';
				}
				if ( count($selected_tags) > 0 ) {
					$url .= '&fq=tags:("' . join('" OR "', $selected_tags) . '")';
				}
				$url .= '&fl=id,title,thumbnail,url,summary,post_type,categories,tags';
				$url .= '&start=' . $start;
				$url .= '&rows=' . $meta['search_result_count'][0];
				$url .= '&facet=true';
				$url .= '&facet.mincount=1';
				$url .= '&facet.field=categories';
				$url .= '&facet.field=tags';
				$url .= '&facet.field=post_type';
				$url .= '&f.categories.facet.sort=index';
				$url .= '&f.tags.facet.sort=index';
				$url .= '&f.post_type.facet.sort=index';
				$url .= '&wt=json';
				if ( $search_profile != '') {
					$url .= '&model="' . $search_profile;
				}
				$args = array(
					'headers' => array(
						'Authorization' => 'Token ' . $token
					)
				);

				$response = wp_remote_get( $url, $args );
				$body = wp_remote_retrieve_body( $response );
				$json = json_decode( $body, true );
				
				if (isset($json['message'])) {
					echo 'Error';
					echo $json['message'];
				}
				else {
					$post_types = array();
					$categories = array();
					$tags = array();

					if ( array_key_exists('categories', $json['facet_counts']['facet_fields'] ) ) {
						$cats = $json['facet_counts']['facet_fields']['categories'];
						for ( $i = 0; $i < count($cats); $i +=2 ) {
							$categories[ $cats[$i] ] = $cats[$i + 1];
						}
					}

					if ( array_key_exists('tags', $json['facet_counts']['facet_fields'] ) ) {
						$tag = $json['facet_counts']['facet_fields']['tags'];
						for ( $i = 0; $i < count($tag); $i +=2 ) {
							$tags[ $tag[$i] ] = $tag[$i + 1];
						}
					}

					if ( array_key_exists('post_type', $json['facet_counts']['facet_fields'] ) ) {
						$types = $json['facet_counts']['facet_fields']['post_type'];
						for ( $i = 0; $i < count($types); $i +=2 ) {
							$post_types[ $types[$i] ] = $types[$i + 1];
						}
					}

					ksort($post_types);
					ksort($categories);
					ksort($tags);
					echo '<div>Showing <strong>' . ($start + 1) . ' - ';
					if ( ($start + $meta['search_result_count'][0]) > $json['response']['numFound']) {
						echo $json['response']['numFound'];
					}
					else {
						echo $start + $meta['search_result_count'][0];
					}
					echo '</strong> of <strong>' . $json['response']['numFound'] . '</strong>';
					if ( $show_search_bar != 'fixed_search' ) {
						echo ' results for <strong>"' . $query . '"</strong>';
					}
					echo '</div>';
					echo '<div class="searchstax_search_search_results">';
					echo '<div class="searchstax_search_search_facets">';
					echo '<div class="searchstax_search_facet">';
					echo '<h4>Content Type</h4>';
					if ( count($post_types) > 10) {
						echo '<input id="searchstax_search_post_type_expander" class="searchstax_search_toggle" type="checkbox">';
						echo '<label for="searchstax_search_post_type_expander" class="searchstax_search_toggle_label">More</label>';
					}
					echo '<div class="searchstax_search_facet_container">';
					foreach ( $post_types as $post_type => $count) {
						echo '<div class="searchstax_search_facet">';
						echo '<form>';
						echo '<input type="hidden" name="searchstax[query]" value="' . $query . '">';
						echo '<input type="hidden" name="post_type" value="' . $post_type . '">';
						echo '<a href="#" class="searchstax_search_facet_link" onClick="parentNode.submit();">' . $post_type . ' (' . $count . ')</a>';
						echo '</form>';
						echo '</div>';
					}
					echo '</div>';
					echo '</div>';
					echo '<div class="searchstax_search_facet">';
					echo '<h4>Categories</h4>';
					if ( count($categories) > 10) {
						echo '<input id="searchstax_search_category_expander" class="searchstax_search_toggle" type="checkbox">';
						echo '<label for="searchstax_search_category_expander" class="searchstax_search_toggle_label">More</label>';
					}
					echo '<div class="searchstax_search_facet_container">';
					foreach ( $categories as $category => $count) {
						echo '<div class="searchstax_search_facet">';
						echo '<form>';
						echo '<input type="hidden" name="searchstax[query]" value="' . $query . '">';
						echo '<input type="hidden" name="category" value="' . $category . '">';
						echo '<a href="#" class="searchstax_search_facet_link" onClick="parentNode.submit();">' . $category . ' (' . $count . ')</a>';
						echo '</form>';
						echo '</div>';
					}
					echo '</div>';
					echo '</div>';
					echo '<div class="searchstax_search_facet">';
					echo '<h4>Tags</h4>';
					if ( count($tags) > 10) {
						echo '<input id="searchstax_search_tags_expander" class="searchstax_search_toggle" type="checkbox">';
						echo '<label for="searchstax_search_tags_expander" class="searchstax_search_toggle_label">More</label>';
					}
					echo '<div class="searchstax_search_facet_container">';
					foreach ( $tags as $tag => $count) {
						echo '<div class="searchstax_search_facet">';
						echo '<form>';
						echo '<input type="hidden" name="searchstax[query]" value="' . $query . '">';
						echo '<input type="hidden" name="tag" value="' . $tag . '">';
						echo '<a href="#" class="searchstax_search_facet_link" onClick="parentNode.submit();">' . $tag . ' (' . $count . ')</a>';
						echo '</form>';
						echo '</div>';
					}
					echo '</div>';
					echo '</div>';
					echo '</div>';
					echo '<div class="searchstax_search_results">';
					if ($meta['search_display'][0] == 'display_grid') {
						echo '<div class="searchstax_search_grid">';
					}
					if ($meta['search_display'][0] == 'display_inline') {
						echo '<div class="searchstax_search_inline">';
					}
					foreach ( $json['response']['docs'] as $doc ) {
						echo '<div class="searchstax_search_result">';
						if ( array_key_exists('thumbnail', $doc) && $doc['thumbnail'] !== 'false') {
							echo '<div class="searchstax_search_thumbnail_frame">';
							echo '<img class="searchstax_search_thumbnail" src="' . $doc['thumbnail'] . '">';
							echo '</div>';
						}
						echo '<div class="searchstax_search_snippet">';
						echo '<h4><a href="' . $doc['url'] . '" class="searchstax_search_result_link">' . $doc['title'] . '</a></h4>';
						echo '<div>' . $doc['summary'] . '</div>';
						if ( array_key_exists('url', $doc) ) {
							echo '<div><a href="' . $doc['url'] . '">' . $doc['url'] . '</a></div>';
						}
						echo '</div>';
						echo '</div>';
					}
					echo '</div>';
					echo '</div>';
					echo '</div>';
					echo '<div class="searchstax_search_result_pagination">';
					echo '<form>';
					echo '<input type="hidden" name="searchstax[query]" value="' . $query . '">';
					echo '<input type="hidden" name="searchStart" value="' . ($start - $meta['search_result_count'][0]) . '">';
					echo '<input type="submit" value="Previous"';
					if ( $start == 0 ) {
						echo ' disabled="true"';
					}
					echo '>';
					echo '</form>';
					echo 'Page ' . (ceil($start / $meta['search_result_count'][0]) + 1) . ' of ' . ceil($json['response']['numFound'] / $meta['search_result_count'][0]);
					echo '<form>';
					echo '<input type="hidden" name="searchstax[query]" value="' . $query . '">';
					echo '<input type="hidden" name="searchStart" value="' . ($start + $meta['search_result_count'][0]) . '">';
					echo '<input type="submit" value="Next"';
					if ( ($start + $meta['search_result_count'][0]) > $json['response']['numFound'] ) {
						echo ' disabled="true"';
					}
					echo '>';
					echo '</form>';
					echo '</div>';
					echo '</div>';
				}
			}
	echo '</div>';
}
if ( $meta['search_config'][0] == 'config_dynamic' ) {
	?>
		<div class="searchstax_search_container">
			<?php if( $show_search_bar != 'fixed_search' ) { ?>
				<div>
					<div class="searchstax_search_search_bar">
						<input id="searchstax_search_dynamic_search_input" class="searchstax_search_search_input" type="text" name="searchstax[query]" autocomplete="off" />
						<button id="searchstax_search_dynamic_search_submit" class="searchstax_search_search_submit" type="submit">
							<div id="searchstax_search_dynamic_label">
								Search
							</div>
							<div id="searchstax_search_dynamic_loader">
								<div class="searchstax_search_loader"></div>
							</div>
						</button>
					</div>
				</div>
			<?php
				}
				else {
					echo '<input id="searchstax_search_dynamic_fixed_search_query" type="hidden" value="' . $fixed_search_query . '" />';
				}
			?>
			<div id="searchstax_search_dynamic_status_message"></div>
			<div id="searchstax_search_dynamic_search_count"></div>
			<input id="searchstax_search_dynamic_post_id" type="hidden" value="<?php echo $post->ID ?>" />
			<div class="searchstax_search_search_results" id="searchstax_search_dynamic_search_results">
				<div id="searchstax_search_dynamic_search_facets" class="searchstax_search_search_facets"></div>
				<div class="searchstax_search_results">
					<?php
					if ($meta['search_display'][0] == 'display_grid') {
						echo '<div id="searchstax_search_dynamic_results" class="searchstax_search_grid"></div>';
					}
					if ($meta['search_display'][0] == 'display_inline') {
						echo '<div id="searchstax_search_dynamic_results" class="searchstax_search_inline"></div>';
					}
					?>
				</div>
			</div>
			<div id="searchstax_search_result_dynamic_pagination" class="searchstax_search_result_pagination"></div>
		</div>
	<?php
}
if ( $meta['search_config'][0] == 'config_searchui' ) {
	$suggest_url = get_option('searchstax_search_searchui_suggest_url');
	$related_token = get_option('searchstax_search_searchui_related_token');
	$related_url = get_option('searchstax_search_searchui_related_url');
	$smart_url = get_option('searchstax_search_searchui_smart_url');
	$analytics_token = get_option('searchstax_search_searchui_analytics_token');
	$app_id = get_option('searchstax_search_searchui_app_id');
	?>
		<div class="searchstax_search_container">
			<div class="searchstax-page-layout-container">
		        <div id="searchstax-feedback-container"></div>
		        <div id="searchstax-input-container"></div>
		        <div id="searchstax-answer-container"></div>
		        <div class="search-details-container">
		          <div id="search-feedback-container"></div>
		          <div class="searchstax-view-styles-container" style="visibility: hidden;">
		            <span class="searchstax-view-styles-text">View Style</span>
		            <div id="toggle-view-style" role="button"  aria-label="View Style Change Button" tabindex="0">
		              <span id="icon-view-style"></span>
		            </div>
		            </div>
		          <div id="search-sorting-container"></div>
		        </div>

		        <div class="searchstax-page-layout-facet-result-container">
		          <div class="searchstax-page-layout-facet-container">
		            <div id="searchstax-facets-container"></div>
		          </div>

		          <div class="searchstax-page-layout-result-container">
		            <div id="searchstax-external-promotions-layout-container"></div>
		            <div id="searchstax-results-container"></div>
		            <div id="searchstax-pagination-container"></div>
		            <div id="searchstax-related-searches-container"></div>
		          </div>
		        </div>
		    </div>
		    <link rel="stylesheet" href="https://static.searchstax.com/studio-js/v4.1.47/css/search-ux.css">
		    <link href="https://static.searchstax.com/studio-js/v4.1.47/css/feedbackWidget.css" rel="stylesheet"/>
		    <script type="module">
				import SearchstaxFeedbackWidget from "https://static.searchstax.com/studio-js/v4.1.47/js/feedbackWidget.mjs";
				function makeId(length) {
					let result = "";
					const characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789";
					const charactersLength = characters.length;
					for (let i = 0; i < length; i++) {
						result += characters.charAt(
							Math.floor(Math.random() * charactersLength)
						);
					}
					return result;
				}

				const config = {
					language: "en",
					searchURL: "<?php echo $select_api ?>",
					suggesterURL: "<?php echo $suggest_url ?>",
					searchAuth: "<?php echo $token ?>",
					trackApiKey: "<?php echo $analytics_token ?>",
					authType: "token",
					relatedSearchesURL: "<?php echo $related_url ?>",
					relatedSearchesAPIKey: "<?php echo $related_token ?>",
					analyticsBaseUrl: "https://analytics-us.searchstax.com",
					questionURL: "<?php echo $smart_url ?>",
					geocodingURL: "https://geocoding.searchstax.com",
					countryCode: "us",
					appId: "<?php echo $app_id ?>",
					analyticsSrc: 'https://static.searchstax.com/studio-js/v4.1.47/js/studio-analytics.js',
					sessionId: makeId(25),
					model: "<?php echo $search_profile ?>",
				};

				const renderConfig = {
					inputWidget: {},
					facetsWidget: {
						itemsPerPageDesktop: 3,
						itemsPerPageMobile: 99,
						facetingType: "and", // "and" | "or" | "showUnavailable" | "tabs"
					},
					resultsWidget: {
						renderMethod: "pagination", //'infiniteScroll' or 'pagination'
					},
				};

				window.onload = (event) => {
					let searchstaxQuery = "";
					var script = document.createElement("script");
					script.src = "https://static.searchstax.com/studio-js/v4.1.47/js/search-ux.js";
					script.onload = function (some) {
						const searchstax = new window["@searchstaxInc/searchstudioUxJs"].Searchstax();

						function searchstaxFeedbackTextAreaOverride() {
							if (!searchstax) {
								return "";
							}
							else {
								return (
									(searchstax.dataLayer.searchObject.query === "undefined"
										? ""
										: searchstax.dataLayer.searchObject.query) +
									" " +
									searchstax.dataLayer.parsedData.getAnswerData
								);
							}
						}

						searchstax.initialize({
							language: config.language,
							model: config.model,
							searchURL: config.searchURL,
							suggesterURL: config.suggesterURL,
							searchAuth: config.searchAuth,
							trackApiKey: config.trackApiKey,
							authType: config.authType,
							analyticsBaseUrl: config.analyticsBaseUrl,
							sessionId: config.sessionId,
							questionURL: config.questionURL,
							analyticsSrc: config.analyticsSrc,
							hooks: {
								beforeSearch: (searchObj) => {
									searchstaxQuery = searchObj.query;
									return searchObj;
								},
								afterSearch: (results) => {
									var container = document.querySelector(
										".searchstax-view-styles-container"
									);
									if (container) {
										if (results.length === 0) {
											container.style.visibility = "hidden";
										} else {
											container.style.visibility = "visible";
										}
									}

									return results;
								},
							},
						});

						searchstax.addSearchLocationWidget("searchstax-location-container", {
							templates: {
								mainTemplate: {
									template: `
										<div class="searchstax-location-input-container" data-test-id="searchstax-location-input-container">
											<div class="searchstax-location-input-wrapper">
												<span class="searchstax-location-input-label">NEAR</span>
												<div class="searchstax-location-input-wrapper-inner">
													<input type="text" id="searchstax-location-input" class="searchstax-location-input" placeholder="Zip, Postal Code or City..." aria-label="Search Location Input" data-test-id="searchstax-location-input" />
													<button id="searchstax-location-get-current-location" class="searchstax-get-current-location-button">Use my current location</button>
												</div>
												{{#shouldShowLocationDistanceDropdown}}
													<span class="searchstax-location-input-label">WITHIN</span>
													<select id="searchstax-location-radius-select" class="searchstax-location-radius-select" aria-label="Search Location Radius Select" data-test-id="searchstax-location-radius-select">
														{{#locationSearchDistanceValues}}
															<option value="{{value}}" {{#isSelected}}selected{{/isSelected}}>{{label}}</option>
														{{/locationSearchDistanceValues}}
													</select>
												{{/shouldShowLocationDistanceDropdown}}
											</div>
										</div>
									`,
								},
							},
							hooks: {
								locationDecode: (term) => {
									const app_id = config.appId;
									const countryCode = config.countryCode || "US";
									return new Promise((resolve) => {
										const geocodingURL = `${config.geocodingURL}/forward?location=${encodeURIComponent(term)}&components=country:us&app_id=${config.appId}`;
											fetch(geocodingURL,{
												headers: {
													'Authorization': `Token ${config.relatedSearchesAPIKey}`,
													'Content-Type': 'application/json'
												}
											})
											.then((response) => response.json())
											.then((data) => {
												if (data.status === "OK" && data.results.length > 0) {
													const result = data.results[0];
													const location = {
														lat: result.geometry.lat,
														lon: result.geometry.lng,
														address: result.formatted_address,
													};
													resolve(location);
												} else {
													resolve({
														address: undefined,
														lat: undefined,
														lon: undefined,
														error: true
													});
												}
											})
											.catch(() => {
												resolve({
													address: undefined,
													lat: undefined,
													lon: undefined,
													error: true
												});
											});
									});
								},
								locationDecodeCoordinatesToAddress: (lat, lon) => {
									return new Promise((resolve) => {
										fetch(
											`${config.geocodingURL}/reverse?location=${lat},${lon}&components=country:us&app_id=${config.appId}`,
											{
												method: "GET",
												headers: {
													Authorization: `Token ${config.relatedSearchesAPIKey}`,
												},
											}
										)
										.then((response) => response.json())
										.then((data) => {
											if (data.status === "OK" && data.results.length > 0) {
												const result = data.results[0];
												resolve({
													address: result.formatted_address,
													lat: lat,
													lon: lon,
													error: false,
												});
											} else {
												resolve({
													address: undefined,
													lat: lat,
													lon: lon,
													error: true,
												});
											}
										})
										.catch(() => {
											resolve({
												address: undefined,
												lat: lat,
												lon: lon,
												error: true,
											});
										});
								});
								}
							},
							locationSearchEnabled: false,
							locationValuesOverride: {
								locationDistanceEnabled: false,
								filterValues: [],
								filterUnit: "",
								locationFilterDefaultValue: ""
							},
						});
						new SearchstaxFeedbackWidget({
							analyticsKey: config.trackApiKey,
							model: config.model,
							containerId: "searchstax-feedback-container",
							lightweight: false,
							mainTemplateOverride: `
								<div class="sf-widget-container">
									<div id="sf-widget" class="sf-main">
									<a role="button" href="#" id="searchstax-open-feedback" class="sf-open-feedback" tabindex="0" aria-label="Open Search Feedback">[ - ] Search Feedback</a>
									<div class="sf-modal-content {{#feedbackOpen}}sf-open{{/feedbackOpen}}{{^feedbackOpen}}sf-close{{/feedbackOpen}}" role="dialog" aria-modal="true" aria-label="search feedback modal">
										<div class="sf-modal-header">
											<h2 class="sf-title">Search Feedback</h2>
											<button class="sf-modal-close" aria-label="Close Search Feedback">
												<span>
													<img alt="" src="data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIGlkPSJDYXBhXzEiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDM4Ni42NjcgMzg2LjY2NyIgaGVpZ2h0PSI1MTIiIHZpZXdCb3g9IjAgMCAzODYuNjY3IDM4Ni42NjciIHdpZHRoPSI1MTIiIGNsYXNzPSIiPjxnPjxwYXRoIGQ9Im0zODYuNjY3IDQ1LjU2NC00NS41NjQtNDUuNTY0LTE0Ny43NyAxNDcuNzY5LTE0Ny43NjktMTQ3Ljc2OS00NS41NjQgNDUuNTY0IDE0Ny43NjkgMTQ3Ljc2OS0xNDcuNzY5IDE0Ny43NyA0NS41NjQgNDUuNTY0IDE0Ny43NjktMTQ3Ljc2OSAxNDcuNzY5IDE0Ny43NjkgNDUuNTY0LTQ1LjU2NC0xNDcuNzY4LTE0Ny43N3oiIGRhdGEtb3JpZ2luYWw9IiMwMDAwMDAiIGNsYXNzPSJhY3RpdmUtcGF0aCIgc3R5bGU9ImZpbGw6I2Q2MzIwMiIgZGF0YS1vbGRfY29sb3I9IiMwMDAwMDAiPjwvcGF0aD48L2c+IDwvc3ZnPg==" />
												</span>
											</button>
										</div>
										<form id="sf-rating-form">
											<div class="sf-modal-body">
												<div class="form-group">
													<fieldset class="form-group">
														<legend>How would you rate your search experience?</legend>
														<div class="sf-rate-experience">
															{{#ratings}}
															<div class="sf-custom-control">
																<input type="radio" id="sf-rate-{{index}}" tabindex="0" name="sf-rating" class="sf-custom-control-input" value="{{index}}" {{#isSelected}}checked{{/isSelected}}>
																<label for="sf-rate-{{index}}" class="sf-custom-control-label">{{index}}</label>
															</div>
															{{/ratings}}
														</div>
													</fieldset>
													<div class="sf-rate">
														<p><small>0 = Very Dissatisfied</small></p>
														<p><small>10 = Very Satisfied</small></p>
													</div>
													<div class="sf-error-rating alert alert-danger {{#errors.rating}}sf-show{{/errors.rating}}{{^errors.rating}}sf-hide{{/errors.rating}}">
														Please Rate your experience.
													</div>
												</div>
												<div class="sf-comments sf-form-group">
													<label>Comments <small>(Optional)</small> <small> (<span class="searchstax-characters-remaining-container">2000</span> characters remaining)</small></label>
													<textarea aria-label="Comments (Optional)" maxlength="{{maxLength}}" class="sf-form-control" id="sf-comments" placeholder="Enter any comments relating to your search experience">{{feedbackTextArea}}</textarea>
												</div>
												<div class="sf-email sf-form-group">
													<label for="sf-email">Email <small>(Optional)</small></label>
													<input class="sf-form-control" type="email" id="sf-email" placeholder="Enter an email address if you want a response" value="{{emailInput}}">
													<div class="sf-error-email alert alert-danger {{#errors.email}}sf-show{{/errors.email}}{{^errors.email}}sf-hide{{/errors.email}}">
														Please enter a valid email id
													</div>
												</div>
											</div>
											<div class="sf-modal-footer">
												<a href="http://searchstax.com/" target="_blank" class="left" tabindex="0" aria-label="Searchstax website link">
													<img tabindex="-1" alt="Searchstax logo" src="data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiPz4KPCEtLSBHZW5lcmF0b3I6IEFkb2JlIElsbHVzdHJhdG9yIDI0LjIuMCwgU1ZHIEV4cG9ydCBQbHVnLUluIC4gU1ZHIFZlcnNpb246IDYuMDAgQnVpbGQgMCkgIC0tPgo8c3ZnIHZlcnNpb249IjEuMSIgaWQ9IkxheWVyXzEiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgeG1sbnM6eGxpbms9Imh0dHA6Ly93d3cudzMub3JnLzE5OTkveGxpbmsiIHg9IjBweCIgeT0iMHB4IgoJIHZpZXdCb3g9IjAgMCAxMzEuNSAzOS44IiBzdHlsZT0iZW5hYmxlLWJhY2tncm91bmQ6bmV3IDAgMCAxMzEuNSAzOS44OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+CjxzdHlsZSB0eXBlPSJ0ZXh0L2NzcyI+Cgkuc3Qwe2ZpbGw6I2Q2MzIwMjt9Cgkuc3Qxe2ZpbGw6IzMzNDc1QTt9Cgkuc3Qye2VuYWJsZS1iYWNrZ3JvdW5kOm5ldyAgICA7fQo8L3N0eWxlPgo8Zz4KCTxnIGlkPSJsb2dvX3g1Rl9jb2xvcl8xXyI+CgkJPGc+CgkJCTxnPgoJCQkJPGcgaWQ9IlhNTElEXzhfIj4KCQkJCQk8cGF0aCBjbGFzcz0ic3QwIiBkPSJNMC4zLDMxLjhMMS44LDMxYzAuMS0wLjEsMC4zLTAuMSwwLjUsMGw3LjQsNC4yYzAuMSwwLjEsMC4zLDAuMSwwLjUsMGw3LjMtNC4yYzAuMi0wLjEsMC4zLTAuMSwwLjUsMAoJCQkJCQlsMS40LDAuOWMwLjMsMC4yLDAuMywwLjYsMCwwLjhsLTkuMiw1LjRjLTAuMSwwLjEtMC4zLDAuMS0wLjUsMGwtOS40LTUuNEMwLDMyLjUsMCwzMiwwLjMsMzEuOHoiLz4KCQkJCTwvZz4KCQkJCTxnIGlkPSJYTUxJRF83XyI+CgkJCQkJPHBhdGggY2xhc3M9InN0MCIgZD0iTTAuMywyNi41bDEuNi0wLjljMC4xLTAuMSwwLjMtMC4xLDAuNSwwbDcuMiw0LjJjMC4xLDAuMSwwLjMsMC4xLDAuNSwwbDcuMy00LjJjMC4xLTAuMSwwLjItMC4yLDAuMi0wLjQKCQkJCQkJVjI0YzAtMC40LTAuNC0wLjYtMC43LTAuNGwtNi43LDMuOWMtMC4xLDAuMS0wLjMsMC4xLTAuNSwwTDAuMiwyMmMtMC4zLTAuMi0wLjMtMC42LDAtMC44bDkuMy01LjVjMC4xLTAuMSwwLjMtMC4xLDAuNSwwCgkJCQkJCWw1LjMsM2MwLjMsMC4yLDAuMywwLjYsMCwwLjhsLTEuNCwwLjljLTAuMiwwLjEtMC4zLDAuMS0wLjUsMGwtMy40LTJjLTAuMi0wLjEtMC4zLTAuMS0wLjUsMGwtNC40LDIuN2MtMC4zLDAuMi0wLjMsMC42LDAsMC44CgkJCQkJCWw0LjQsMi42YzAuMSwwLjEsMC4zLDAuMSwwLjUsMGw3LjMtNC4yYzAuMS0wLjEsMC4zLTAuMSwwLjUsMGwyLDEuMWMwLjIsMC4xLDAuMiwwLjIsMC4yLDAuNHY0LjdjMCwwLjItMC4xLDAuMy0wLjIsMC40CgkJCQkJCWwtOS42LDUuN2MtMC4xLDAuMS0wLjMsMC4xLTAuNSwwbC05LjQtNS41Qy0wLjEsMjcuMi0wLjEsMjYuNywwLjMsMjYuNXoiLz4KCQkJCTwvZz4KCQkJPC9nPgoJCQk8ZyBpZD0iRElOX3g1Rl9OZXh0X3g1Rl9MVF94NUZfUHJvX3g1Rl9MaWdodF94MEFfXzFfIj4KCQkJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik0yNC45LDMxLjNjLTAuMS0wLjEtMC4xLTAuMiwwLTAuMmwwLjQtMC41YzAuMS0wLjEsMC4yLTAuMSwwLjIsMGMwLjcsMC42LDEuOSwxLjEsMy4yLDEuMQoJCQkJCWMxLjgsMCwyLjgtMC45LDIuOC0yLjJjMC0xLjEtMC42LTEuOS0yLjYtMi4xbC0wLjUtMC4xYy0yLjEtMC4zLTMuMi0xLjMtMy4yLTIuOWMwLTEuOSwxLjQtMy4xLDMuNS0zLjFjMS4yLDAsMi40LDAuNCwzLjEsMC45CgkJCQkJYzAuMSwwLDAuMSwwLjEsMCwwLjJsLTAuMywwLjVjLTAuMSwwLjEtMC4xLDAuMS0wLjIsMGMtMC45LTAuNS0xLjctMC44LTIuNy0wLjhjLTEuNiwwLTIuNSwwLjktMi41LDIuMWMwLDEuMSwwLjcsMS44LDIuNiwyLjEKCQkJCQlsMC41LDAuMWMyLjIsMC4zLDMuMiwxLjMsMy4yLDNjMCwxLjktMS4zLDMuMi0zLjksMy4yQzI3LjEsMzIuNiwyNS43LDMyLDI0LjksMzEuM3oiLz4KCQkJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik0zNi42LDIxLjZjMC0wLjEsMC4xLTAuMiwwLjItMC4yaDYuM2MwLjEsMCwwLjIsMC4xLDAuMiwwLjJ2MC42YzAsMC4xLTAuMSwwLjItMC4yLDAuMmgtNQoJCQkJCWMtMC4zLDAtMC42LDAuMy0wLjYsMC42djNjMCwwLjMsMC4zLDAuNiwwLjYsMC42aDQuMmMwLjEsMCwwLjIsMC4xLDAuMiwwLjJ2MC42YzAsMC4xLTAuMSwwLjItMC4yLDAuMmgtNC4yCgkJCQkJYy0wLjMsMC0wLjYsMC4zLTAuNiwwLjZWMzFjMCwwLjMsMC4zLDAuNiwwLjYsMC42aDVjMC4xLDAsMC4yLDAuMSwwLjIsMC4ydjAuNmMwLDAuMS0wLjEsMC4yLTAuMiwwLjJoLTYuMwoJCQkJCWMtMC4xLDAtMC4yLTAuMS0wLjItMC4yVjIxLjZ6Ii8+CgkJCQk8cGF0aCBjbGFzcz0ic3QxIiBkPSJNNTAuMSwyMS42YzAtMC4xLDAuMS0wLjIsMC4yLTAuMmgwLjZjMC4xLDAsMC4yLDAuMSwwLjIsMC4ybDMuOCwxMC43YzAsMC4xLDAsMC4yLTAuMSwwLjJoLTAuNwoJCQkJCWMtMC4xLDAtMC4yLDAtMC4yLTAuMkw1MywyOS45Yy0wLjEtMC4yLTAuMi0wLjMtMC40LTAuM2gtNC4xYy0wLjIsMC0wLjQsMC4xLTAuNCwwLjNsLTAuOSwyLjRjMCwwLjEtMC4xLDAuMi0wLjIsMC4yaC0wLjYKCQkJCQljLTAuMSwwLTAuMS0wLjEtMC4xLTAuMkw1MC4xLDIxLjZ6IE01MS45LDI4LjdjMC4zLDAsMC42LTAuMywwLjQtMC42TDUwLjUsMjNsMCwwbC0xLjgsNWMtMC4xLDAuMywwLjEsMC42LDAuNCwwLjZoMi44VjI4Ljd6IgoJCQkJCS8+CgkJCQk8cGF0aCBjbGFzcz0ic3QxIiBkPSJNNjUsMzIuNGMtMC4xLDAtMC4xLDAtMC4yLTAuMWwtMi4zLTQuOGgtMC4ySDYwYy0wLjMsMC0wLjYsMC4zLTAuNiwwLjZ2NC4yYzAsMC4xLTAuMSwwLjItMC4yLDAuMmgtMC42CgkJCQkJYy0wLjEsMC0wLjItMC4xLTAuMi0wLjJWMjEuNmMwLTAuMSwwLjEtMC4yLDAuMi0wLjJoMy43YzIuMSwwLDMuNCwxLjIsMy40LDNjMCwxLjMtMC42LDIuMy0xLjgsMi44Yy0wLjMsMC4xLTAuNCwwLjQtMC4yLDAuNgoJCQkJCWwyLjEsNC40YzAuMSwwLjEsMCwwLjItMC4xLDAuMkg2NUw2NSwzMi40eiBNNjQuOCwyNC41YzAtMS40LTAuOS0yLjItMi40LTIuMkg2MGMtMC4zLDAtMC42LDAuMy0wLjYsMC42djMuMgoJCQkJCWMwLDAuMywwLjMsMC42LDAuNiwwLjZoMi4zQzYzLjksMjYuNiw2NC44LDI1LjksNjQuOCwyNC41eiIvPgoJCQkJPHBhdGggY2xhc3M9InN0MSIgZD0iTTY5LjYsMjYuOWMwLTAuOCwwLTEuNSwwLjEtMS45YzAuMi0yLjMsMi4zLTQuMSw0LjYtMy42YzEuMSwwLjIsMS45LDAuOSwyLjQsMS45YzAsMC4xLDAsMC4yLDAsMC4yCgkJCQkJbC0wLjUsMC4zYy0wLjEsMC0wLjIsMC0wLjItMC4xYy0wLjUtMC45LTEuMy0xLjYtMi41LTEuNmMtMS4zLDAtMi4yLDAuNi0yLjYsMS44Yy0wLjEsMC40LTAuMiwxLjEtMC4yLDNjMCwxLjgsMC4xLDIuNSwwLjIsMwoJCQkJCWMwLjQsMS4yLDEuMiwxLjgsMi42LDEuOGMxLjIsMCwyLTAuNiwyLjUtMS42YzAtMC4xLDAuMS0wLjEsMC4yLTAuMWwwLjUsMC4zYzAuMSwwLDAuMSwwLjEsMCwwLjJjLTAuNiwxLjMtMS44LDItMy4zLDIKCQkJCQljLTEuNywwLTIuOS0wLjgtMy41LTIuNUM2OS43LDI5LjYsNjkuNiwyOC44LDY5LjYsMjYuOXoiLz4KCQkJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik04MC42LDIxLjZjMC0wLjEsMC4xLTAuMiwwLjItMC4yaDAuNmMwLjEsMCwwLjIsMC4xLDAuMiwwLjJ2NC4yYzAsMC4zLDAuMywwLjYsMC42LDAuNmg0LjUKCQkJCQljMC4zLDAsMC42LTAuMywwLjYtMC42di00LjJjMC0wLjEsMC4xLTAuMiwwLjItMC4yaDAuNmMwLjEsMCwwLjIsMC4xLDAuMiwwLjJ2MTAuN2MwLDAuMS0wLjEsMC4yLTAuMiwwLjJoLTAuNgoJCQkJCWMtMC4xLDAtMC4yLTAuMS0wLjItMC4ydi00LjRjMC0wLjMtMC4zLTAuNi0wLjYtMC42aC00LjVjLTAuMywwLTAuNiwwLjMtMC42LDAuNnY0LjRjMCwwLjEtMC4xLDAuMi0wLjIsMC4yaC0wLjYKCQkJCQljLTAuMSwwLTAuMi0wLjEtMC4yLTAuMlYyMS42TDgwLjYsMjEuNnoiLz4KCQkJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik05Mi4zLDMxLjNjLTAuMS0wLjEtMC4xLTAuMiwwLTAuMmwwLjQtMC41YzAuMS0wLjEsMC4yLTAuMSwwLjIsMGMwLjcsMC42LDEuOSwxLjEsMy4yLDEuMQoJCQkJCWMxLjgsMCwyLjgtMC45LDIuOC0yLjJjMC0xLjEtMC42LTEuOS0yLjYtMi4xbC0wLjUtMC4xYy0yLjEtMC4zLTMuMi0xLjMtMy4yLTIuOWMwLTEuOSwxLjQtMy4xLDMuNS0zLjFjMS4yLDAsMi40LDAuNCwzLjEsMC45CgkJCQkJYzAuMSwwLDAuMSwwLjEsMCwwLjJsLTAuMywwLjVjLTAuMSwwLjEtMC4xLDAuMS0wLjIsMGMtMC45LTAuNS0xLjctMC44LTIuNy0wLjhjLTEuNiwwLTIuNSwwLjktMi41LDIuMWMwLDEuMSwwLjcsMS44LDIuNiwyLjEKCQkJCQlsMC41LDAuMWMyLjIsMC4zLDMuMiwxLjMsMy4yLDNjMCwxLjktMS4zLDMuMi0zLjksMy4yQzk0LjUsMzIuNiw5MywzMiw5Mi4zLDMxLjN6Ii8+CgkJCQk8cGF0aCBjbGFzcz0ic3QxIiBkPSJNMTA2LjIsMzIuNGMtMC4xLDAtMC4yLTAuMS0wLjItMC4ydi05LjRjMC0wLjMtMC4zLTAuNi0wLjYtMC42aC0yLjdjLTAuMSwwLTAuMi0wLjEtMC4yLTAuMnYtMC42CgkJCQkJYzAtMC4xLDAuMS0wLjIsMC4yLTAuMmg3LjRjMC4xLDAsMC4yLDAuMSwwLjIsMC4yVjIyYzAsMC4xLTAuMSwwLjItMC4yLDAuMmgtMi43Yy0wLjMsMC0wLjYsMC4zLTAuNiwwLjZ2OS40CgkJCQkJYzAsMC4xLTAuMSwwLjItMC4yLDAuMkgxMDYuMkwxMDYuMiwzMi40eiIvPgoJCQkJPHBhdGggY2xhc3M9InN0MSIgZD0iTTExNS41LDIxLjZjMC0wLjEsMC4xLTAuMiwwLjItMC4yaDAuNmMwLjEsMCwwLjIsMC4xLDAuMiwwLjJsMy44LDEwLjdjMCwwLjEsMCwwLjItMC4xLDAuMmgtMC42CgkJCQkJYy0wLjEsMC0wLjIsMC0wLjItMC4ybC0wLjktMi40Yy0wLjEtMC4yLTAuMi0wLjMtMC40LTAuM0gxMTRjLTAuMiwwLTAuNCwwLjEtMC40LDAuM2wtMC45LDIuNGMwLDAuMS0wLjEsMC4yLTAuMiwwLjJoLTAuNgoJCQkJCWMtMC4xLDAtMC4xLTAuMS0wLjEtMC4yTDExNS41LDIxLjZ6IE0xMTcuNCwyOC43YzAuMywwLDAuNi0wLjMsMC40LTAuNkwxMTYsMjNsMCwwbC0xLjgsNS4xYy0wLjEsMC4zLDAuMSwwLjYsMC40LDAuNkgxMTcuNHoiCgkJCQkJLz4KCQkJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik0xMjkuOSwzMi40Yy0wLjEsMC0wLjIsMC0wLjItMC4xbC0yLjgtNC43bDAsMGwtMi44LDQuN2MwLDAuMS0wLjEsMC4xLTAuMiwwLjFoLTAuNwoJCQkJCWMtMC4xLDAtMC4xLTAuMS0wLjEtMC4ybDMuMi01LjNjMC4xLTAuMiwwLjEtMC4zLDAtMC41bC0yLjktNC44Yy0wLjEtMC4xLDAtMC4yLDAuMS0wLjJoMC43YzAuMSwwLDAuMiwwLDAuMiwwLjFsMi41LDQuMmwwLDAKCQkJCQlsMi41LTQuMmMwLjEtMC4xLDAuMS0wLjEsMC4yLTAuMWgwLjdjMC4xLDAsMC4xLDAuMSwwLjEsMC4ybC0yLjksNC44Yy0wLjEsMC4yLTAuMSwwLjMsMCwwLjVsMy4yLDUuM2MwLDAuMSwwLDAuMi0wLjEsMC4yCgkJCQkJSDEyOS45TDEyOS45LDMyLjR6Ii8+CgkJCTwvZz4KCQk8L2c+Cgk8L2c+Cgk8ZyBjbGFzcz0ic3QyIj4KCQk8cGF0aCBjbGFzcz0ic3QxIiBkPSJNMS40LDIuMUMyLDIsMi42LDEuOSwzLjUsMS45YzEsMCwxLjgsMC4yLDIuMywwLjdjMC40LDAuNCwwLjcsMSwwLjcsMS43QzYuNCw1LDYuMiw1LjYsNS44LDYKCQkJQzUuMyw2LjYsNC40LDYuOSwzLjMsNi45Yy0wLjMsMC0wLjYsMC0wLjgtMC4xVjEwaC0xVjIuMXogTTIuNSw2QzIuNyw2LDMsNiwzLjQsNmMxLjMsMCwyLTAuNiwyLTEuN2MwLTEuMS0wLjgtMS42LTEuOS0xLjYKCQkJYy0wLjUsMC0wLjgsMC0xLDAuMVY2eiIvPgoJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik0xMi44LDcuMWMwLDIuMS0xLjUsMy4xLTIuOSwzLjFjLTEuNiwwLTIuOC0xLjItMi44LTNjMC0xLjksMS4zLTMuMSwyLjktMy4xQzExLjYsNC4xLDEyLjgsNS4zLDEyLjgsNy4xegoJCQkgTTguMSw3LjJjMCwxLjMsMC43LDIuMiwxLjgsMi4yYzEsMCwxLjgtMC45LDEuOC0yLjNjMC0xLTAuNS0yLjItMS43LTIuMkM4LjcsNC45LDguMSw2LDguMSw3LjJ6Ii8+CgkJPHBhdGggY2xhc3M9InN0MSIgZD0iTTE0LjQsNC4ybDAuOCwzYzAuMiwwLjYsMC4zLDEuMiwwLjQsMS44aDBjMC4xLTAuNiwwLjMtMS4yLDAuNS0xLjhsMC45LTNIMThsMC45LDIuOQoJCQljMC4yLDAuNywwLjQsMS4zLDAuNSwxLjloMGMwLjEtMC42LDAuMy0xLjIsMC40LTEuOWwwLjgtMi45aDFMMTkuOSwxMGgtMWwtMC45LTIuOGMtMC4yLTAuNi0wLjQtMS4yLTAuNS0xLjloMAoJCQljLTAuMSwwLjctMC4zLDEuMy0wLjUsMS45TDE2LjEsMTBoLTFsLTEuOC01LjhIMTQuNHoiLz4KCQk8cGF0aCBjbGFzcz0ic3QxIiBkPSJNMjMuMyw3LjNjMCwxLjQsMC45LDIsMiwyYzAuOCwwLDEuMi0wLjEsMS42LTAuM2wwLjIsMC44Yy0wLjQsMC4yLTEsMC40LTEuOSwwLjRjLTEuOCwwLTIuOS0xLjItMi45LTIuOQoJCQlzMS0zLjEsMi43LTMuMWMxLjksMCwyLjQsMS43LDIuNCwyLjdjMCwwLjIsMCwwLjQsMCwwLjVIMjMuM3ogTTI2LjQsNi42YzAtMC43LTAuMy0xLjctMS41LTEuN2MtMS4xLDAtMS41LDEtMS42LDEuN0gyNi40eiIvPgoJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik0yOC43LDZjMC0wLjcsMC0xLjMsMC0xLjhoMC45bDAsMS4xaDBjMC4zLTAuOCwwLjktMS4zLDEuNi0xLjNjMC4xLDAsMC4yLDAsMC4zLDB2MWMtMC4xLDAtMC4yLDAtMC40LDAKCQkJYy0wLjcsMC0xLjMsMC42LTEuNCwxLjRjMCwwLjEsMCwwLjMsMCwwLjVWMTBoLTFWNnoiLz4KCQk8cGF0aCBjbGFzcz0ic3QxIiBkPSJNMzMuMSw3LjNjMCwxLjQsMC45LDIsMiwyYzAuOCwwLDEuMi0wLjEsMS42LTAuM2wwLjIsMC44Yy0wLjQsMC4yLTEsMC40LTEuOSwwLjRjLTEuOCwwLTIuOS0xLjItMi45LTIuOQoJCQlzMS0zLjEsMi43LTMuMWMxLjksMCwyLjQsMS43LDIuNCwyLjdjMCwwLjIsMCwwLjQsMCwwLjVIMzMuMXogTTM2LjIsNi42YzAtMC43LTAuMy0xLjctMS41LTEuN2MtMS4xLDAtMS41LDEtMS42LDEuN0gzNi4yeiIvPgoJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik00My42LDEuNXY3YzAsMC41LDAsMS4xLDAsMS41aC0wLjlsMC0xaDBjLTAuMywwLjYtMSwxLjEtMiwxLjFjLTEuNCwwLTIuNS0xLjItMi41LTNjMC0xLjksMS4yLTMuMSwyLjYtMy4xCgkJCWMwLjksMCwxLjUsMC40LDEuOCwwLjloMFYxLjVINDMuNnogTTQyLjUsNi42YzAtMC4xLDAtMC4zLDAtMC40Yy0wLjItMC43LTAuNy0xLjItMS41LTEuMmMtMS4xLDAtMS43LDEtMS43LDIuMgoJCQljMCwxLjIsMC42LDIuMSwxLjcsMi4xYzAuNywwLDEuNC0wLjUsMS41LTEuM2MwLTAuMSwwLTAuMywwLTAuNVY2LjZ6Ii8+CgkJPHBhdGggY2xhc3M9InN0MSIgZD0iTTQ3LjgsMTBjMC0wLjQsMC0xLDAtMS41di03aDF2My42aDBjMC40LTAuNiwxLTEuMSwyLTEuMWMxLjQsMCwyLjUsMS4yLDIuNCwzYzAsMi4xLTEuMywzLjEtMi42LDMuMQoJCQljLTAuOCwwLTEuNS0wLjMtMS45LTEuMWgwbDAsMUg0Ny44eiBNNDguOSw3LjdjMCwwLjEsMCwwLjMsMCwwLjRjMC4yLDAuNywwLjgsMS4yLDEuNiwxLjJjMS4xLDAsMS44LTAuOSwxLjgtMi4yCgkJCWMwLTEuMi0wLjYtMi4yLTEuNy0yLjJjLTAuNywwLTEuNCwwLjUtMS42LDEuM2MwLDAuMS0wLjEsMC4zLTAuMSwwLjRWNy43eiIvPgoJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik01NSw0LjJsMS4zLDMuNGMwLjEsMC40LDAuMywwLjgsMC40LDEuMmgwYzAuMS0wLjMsMC4yLTAuOCwwLjQtMS4ybDEuMi0zLjRoMS4xbC0xLjYsNC4xYy0wLjgsMi0xLjMsMy0yLDMuNgoJCQljLTAuNSwwLjUtMSwwLjYtMS4zLDAuN2wtMC4zLTAuOWMwLjMtMC4xLDAuNi0wLjMsMC45LTAuNWMwLjMtMC4yLDAuNi0wLjYsMC45LTEuMkM1NiwxMCw1Niw5LjksNTYsOS45YzAtMC4xLDAtMC4xLTAuMS0wLjMKCQkJbC0yLjEtNS4zSDU1eiIvPgoJCTxwYXRoIGNsYXNzPSJzdDEiIGQ9Ik02MC4zLDUuMmMwLTAuNCwwLjMtMC44LDAuNy0wLjhjMC40LDAsMC43LDAuMywwLjcsMC44YzAsMC40LTAuMywwLjctMC43LDAuN0M2MC42LDUuOSw2MC4zLDUuNiw2MC4zLDUuMnoKCQkJIE02MC4zLDkuNGMwLTAuNCwwLjMtMC44LDAuNy0wLjhjMC40LDAsMC43LDAuMywwLjcsMC44YzAsMC40LTAuMywwLjctMC43LDAuN0M2MC42LDEwLjIsNjAuMyw5LjksNjAuMyw5LjR6Ii8+Cgk8L2c+CjwvZz4KPC9zdmc+Cg==" class="sf-foot-logo" tabindex="-1">
												</a>
												{{#submitted}}
												<span class="sf-btn sf-btn-sent sf-btn-success" aria-disabled="true">&check; Sent</span>
												{{/submitted}}
												{{^submitted}}
												<button class="sf-btn sf-btn-primary js-send-feedback" type="button" tabindex="0">
													<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120" viewBox="0 0 100 100" class="sf-checkmark hidden">
															<circle class="sf-circle" cx="50" cy="50" r="46"/>
															<polyline class="sf-tick" points="25,55 45,70 75,33" pathLength="48"/>
												 </svg>
												 <span class="sf-btn-text">Send</span>
												</button>
												{{/submitted}}
											</div>
										</form>
									</div>
								</div>
								<div class="sf-widget-container">
							`,
							analyticsSrc: 'https://static.searchstax.com/studio-js/v4.1.47/js/studio-analytics.js'
						});

						searchstax.addAnswerWidget("searchstax-answer-container", {
							showMoreAfterWordCount: 100,
							templates: {
								main: {
									template: `
										{{#shouldShowAnswer}}
										<div class="searchstax-answer-wrap">
												<div class="searchstax-answer-icon"></div>
												<div>
														<div class="searchstax-answer-container {{#showMoreButtonVisible}}searchstax-answer-show-more{{/showMoreButtonVisible}}">
																<div class="searchstax-answer-title">Smart Answers</div>
																{{#shouldShowAnswerError}}
																		<div class="searchstax-answer-error">{{{answerErrorMessage}}}</div>
																{{/shouldShowAnswerError}}
																<div class="searchstax-answer-description">
																		{{{fullAnswerFormatted}}}
																		{{^showMoreButtonVisible}}
																				{{#answerLoading}}
																						<div class="searchstax-answer-loading"></div>
																				{{/answerLoading}}
																		{{/showMoreButtonVisible}}
																</div>

														</div>

														{{#showMoreButtonVisible}}
																<div class="searchstax-answer-load-more-button-container">
																		{{#answerLoading}}
																				<div class="searchstax-answer-loading"></div>
																		{{/answerLoading}}
																		<button class="searchstax-answer-load-more-button">Read More</button>
																</div>
														{{/showMoreButtonVisible}}
												</div>
												<div class="searchstax-answer-footer">
														<div id="feedbackWidgetContainer"></div>
														<div class="searchstax-lightweight-widget-separator-inline"></div>
														<p class="searchstax-disclaimer">Generative AI is Experimental</p>
												</div>
												</div>
										{{/shouldShowAnswer}}
										`,
								},
							},
							feedbackwidget: {
								renderFeedbackWidget: true,
								thumbsUpValue: 10,
								thumbsDownValue: 0,
								lightweightTemplateOverride: `
									 <div class="searchstax-lightweight-widget-container">
									 	<div class="searchstax-lightweight-widget-thumbs-up {{#thumbsUpActive}}active{{/thumbsUpActive}}">
									 	<svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path d="M4.85079 7.59996L7.83141 1C8.4243 1 8.9929 1.23178 9.41213 1.64436C9.83136 2.05694 10.0669 2.61651 10.0669 3.19999V6.1333H14.2845C14.5005 6.13089 14.7145 6.17474 14.9116 6.26179C15.1087 6.34885 15.2842 6.47704 15.426 6.63747C15.5677 6.79791 15.6723 6.98676 15.7326 7.19094C15.7928 7.39513 15.8072 7.60975 15.7748 7.81996L14.7465 14.4199C14.6926 14.7696 14.5121 15.0884 14.2382 15.3175C13.9643 15.5466 13.6156 15.6706 13.2562 15.6666H4.85079M4.85079 7.59996V15.6666M4.85079 7.59996H2.61531C2.22006 7.59996 1.84099 7.75448 1.5615 8.02953C1.28201 8.30458 1.125 8.67763 1.125 9.06661V14.1999C1.125 14.5889 1.28201 14.9619 1.5615 15.237C1.84099 15.512 2.22006 15.6666 2.61531 15.6666H4.85079"  stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</div>
									<div class="searchstax-lightweight-widget-separator"></div>
									<div class="searchstax-lightweight-widget-thumbs-down {{#thumbsDownActive}}active{{/thumbsDownActive}}">
										<svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path d="M12.1492 9.06801L9.16859 15.668C8.5757 15.668 8.0071 15.4362 7.58787 15.0236C7.16864 14.611 6.93311 14.0515 6.93311 13.468V10.5347H2.71552C2.4995 10.5371 2.28552 10.4932 2.08842 10.4062C1.89132 10.3191 1.71581 10.1909 1.57405 10.0305C1.43229 9.87006 1.32766 9.6812 1.26743 9.47702C1.2072 9.27284 1.19279 9.05822 1.22521 8.84801L2.25353 2.24806C2.30742 1.89833 2.48793 1.57955 2.76179 1.35046C3.03566 1.12136 3.38443 0.997398 3.74384 1.0014H12.1492M12.1492 9.06801V1.0014M12.1492 9.06801H14.3847C14.7799 9.06801 15.159 8.91349 15.4385 8.63844C15.718 8.36339 15.875 7.99034 15.875 7.60135V2.46805C15.875 2.07907 15.718 1.70602 15.4385 1.43097C15.159 1.15592 14.7799 1.0014 14.3847 1.0014H12.1492"  stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
										</svg>
									</div>
									</div>
								`,
							}
						});

						searchstax.addSearchFeedbackWidget("search-feedback-container", {
							templates: {
								main: {
									template: `
										{{#searchExecuted}}
												<a href="#searchstax-search-results" data-test-id="searchstax-skip" class="searchstax-skip">Skip to results section</a>
												<h4 class="searchstax-feedback-container" data-test-id="searchstax-feedback-container">
													{{#hasResults}}
														 <span> Showing <b>{{startResultIndex}} - {{endResultIndex}}</b> </span> of <b>{{totalResults}}</b> results {{#searchTerm}} for "<b>{{searchTerm}}</b>" {{/searchTerm}}
															<div class="searchstax-feedback-container-suggested" data-test-id="searchstax-feedback-container-suggested">
																{{#autoCorrectedQuery}}
																	Search instead for <a href="#" aria-label="Search instead for: {{originalQuery}}" class="searchstax-feedback-original-query" data-test-id="searchstax-feedback-original-query">{{originalQuery}}</a>
																{{/autoCorrectedQuery}}
															</div>
														{{/hasResults}}
												</h4>
										{{/searchExecuted}}
									`,
									originalQueryClass: `searchstax-feedback-original-query`,
								},
							},
						});

						searchstax.addSearchInputWidget("searchstax-input-container", {
							templates: {
								mainTemplate: {
									template: `
										<div class="searchstax-search-input-container searchstax-search-input-container-new {{#locationEnabled}}searchstax-alternative-render{{/locationEnabled}}">
											<div class="searchstax-search-input-wrapper">
												<input type="text" id="searchstax-search-input" class="searchstax-search-input" placeholder="SEARCH FOR..." aria-label="Search" />
											</div>
											<div id="searchstax-location-container" class="searchstax-location-container"></div>
											<button class="searchstax-spinner-icon" id="searchstax-search-input-action-button" aria-label="search" role="button"></button>
										</div>
									`,
									searchInputId: "searchstax-search-input",
								},
								autosuggestItemTemplate: {
									template: `
										<div class="searchstax-autosuggest-item-term-container">{{{term}}}</div>
									`,
								},
							},
						});

						searchstax.addFacetsWidget("searchstax-facets-container", {
							facetingType: renderConfig.facetsWidget.facetingType,
							itemsPerPageDesktop: renderConfig.facetsWidget.itemsPerPageDesktop,
							itemsPerPageMobile: renderConfig.facetsWidget.itemsPerPageMobile,
							templates: {
								mainTemplateDesktop: {
									template: `
										{{#hasResultsOrExternalPromotions}}
											<div class="searchstax-facets-container-desktop"></div>
										{{/hasResultsOrExternalPromotions}}
									`,
									facetsContainerId: "",
								},
								mainTemplateMobile: {
									template: `
										<div class="searchstax-facets-pills-container">
											<div class="searchstax-facets-pills-selected">
											</div>
										</div>
										<div class="searchstax-facets-mobile-overlay {{#overlayOpened}} searchstax-show{{/overlayOpened}}" data-test-id="searchstax-facets-mobile-overlay">
											<div class="searchstax-facets-mobile-overlay-header">
												<div class="searchstax-facets-mobile-overlay-header-title">Filter By</div>
												<div class="searchstax-search-close" tabindex="0" aria-label="close overlay" role="button" data-test-id="searchstax-search-close"></div>
											</div>
											<div class="searchstax-facets-container-mobile"></div>
											<button class="searchstax-facets-mobile-overlay-done" data-test-id="searchstax-facets-mobile-overlay-done">Done</button>
										</div>
									`,
									facetsContainerClass: `searchstax-facets-container-mobile`,
									closeOverlayTriggerClasses: ["searchstax-facets-mobile-overlay-done","searchstax-search-close",],
									filterByContainerClass: `searchstax-facets-pills-container`,
									selectedFacetsContainerClass: `searchstax-facets-pills-selected`,
								},
								showMoreButtonContainerTemplate: {
									template: `
										<div class="searchstax-facet-show-more-container" data-test-id="searchstax-facet-show-more-container">
											{{#showingAllFacets}}
												<div class="searchstax-facet-show-less-button searchstax-facet-show-button" tabindex="0" data-test-id="searchstax-facet-show-less-button" data-focus="{{focusId}}" role="button">less</div>
											{{/showingAllFacets}}
											{{^showingAllFacets}}
												<div class="searchstax-facet-show-more-button  searchstax-facet-show-button" tabindex="0" data-test-id="searchstax-facet-show-more-button" data-focus="{{focusId}}" role="button">more {{onShowMoreLessClick}}</div>
											{{/showingAllFacets}}
										</div>
									`,
									showMoreButtonClass: `searchstax-facet-show-more-container`,
									},
								facetItemContainerTemplate: {
									template: `
										<div>
											<div class="searchstax-facet-title-container" data-test-id="searchstax-facet-title-container">
													<div class="searchstax-facet-title" aria-label="Facet group: {{label}}" tabindex="0" role="button">
													{{label}}
													</div>
													<div class="searchstax-facet-title-arrow active"></div>
											</div>
											<div class="searchstax-facet-values-container"></div>
										</div>
									`,
									facetListTitleContainerClass: `searchstax-facet-title-container`,
									facetListTitleContainerInner: `searchstax-facet-title`,
									facetListContainerClass: `searchstax-facet-values-container`,
								},
								clearFacetsTemplate: {
									template: `
										{{#shouldShow}}
											<div class="searchstax-facets-pill searchstax-clear-filters searchstax-facets-pill-clear-all" tabindex="0" role="button" data-test-id="searchstax-facets-pill-clear-all">
											<div class="searchstax-facets-pill-label">Clear Filters</div>
											</div>
										{{/shouldShow}}
									`,
									containerClass: `searchstax-facets-pill-clear-all`,
								},
								facetItemTemplate: {
										template: `
											<div class="searchstax-facet-input">
												<input type="checkbox" class="searchstax-facet-input-checkbox" data-test-id="searchstax-facet-input-checkbox" {{#disabled}}disabled{{/disabled}} {{#isChecked}}checked{{/isChecked}} aria-label="{{value}} {{count}}" tabindex="0"/>
											</div>
											<div class="searchstax-facet-value-label" data-test-id="searchstax-facet-value-label">{{value}}</div>
											<div class="searchstax-facet-value-count" data-test-id="searchstax-facet-value-count">({{count}})</div>
										`,
										inputCheckboxClass: `searchstax-facet-input-checkbox`,
										checkTriggerClasses: ["searchstax-facet-value-label","searchstax-facet-value-count",],
								},
								filterByTemplate: {
										template: `
											<div class="searchstax-facets-pill searchstax-facets-pill-filter-by" tabindex="0" role="button" data-test-id="searchstax-facets-pill-filter-by" >
												<div class="searchstax-facets-pill-label">Filter By</div>
											</div>
										`,
										containerClass: `searchstax-facets-pill-filter-by`,
								},
								selectedFacetsTemplate: {
										template: `
											<div class="searchstax-facets-pill searchstax-facets-pill-facets" tabindex="0" role="button" data-test-id="searchstax-facets-pill-facets">
												<div class="searchstax-facets-pill-label">{{value}} ({{count}})</div>
												<div class="searchstax-facets-pill-icon-close"></div>
											</div>
										`,
										containerClass: `searchstax-facets-pill-facets`,
								},

							}
						});

						searchstax.addSearchSortingWidget("search-sorting-container", {
							templates: {
								main: {
									template: `
										{{#searchExecuted}}
										{{#hasResultsOrExternalPromotions}}
										<div class="searchstax-sorting-container" data-test-id="searchstax-sorting-container">
												<label class="searchstax-sorting-label" data-test-id="searchstax-sorting-label" for="searchstax-search-order-select">Sort By</label>
												<select id="searchstax-search-order-select" class="searchstax-search-order-select" data-test-id="searchstax-search-order-select" >
												{{#sortOptions}}
														<option value="{{key}}">
														{{value}}
														</option>
												{{/sortOptions}}
												</select>
										</div>
										{{/hasResultsOrExternalPromotions}}
										{{/searchExecuted}}
									`,
									selectId: `searchstax-search-order-select`,
								},
							},
						});

						searchstax.addSearchResultsWidget("searchstax-results-container", {
							templates: {
								mainTemplate: {
									template: `
										<section aria-label="search results container" tabindex="0">
												<div class="searchstax-search-results-container" id="searchstax-search-results-container" data-test-id="searchstax-search-results-container">
														<div class="searchstax-search-results" id="searchstax-search-results"></div>
												</div>
										</section>
									`,
									searchResultsContainerId:
										"searchstax-search-results",
								},
								searchResultTemplate: {
									template: `
										<a href="{{url}}" data-searchstax-unique-result-id="{{uniqueId}}" data-test-id="searchstax-result-item-link" class="searchstax-result-item-link searchstax-result-item-link-wrapping" tabindex="0" aria-labelledby="title-{{uniqueId}}">
										<div class="searchstax-search-result searchstax-search-result-wrapping {{#thumbnail}} has-thumbnail {{/thumbnail}}">
												{{#promoted}}
														<div class="searchstax-search-result-promoted" data-test-id="searchstax-search-result-promoted"></div>
												{{/promoted}}

												{{#ribbon}}
														<div class="searchstax-search-result-ribbon" data-test-id="searchstax-search-result-ribbon">
														{{{ribbon}}}
														</div>
												{{/ribbon}}

												{{#thumbnail}}
														<img alt="" src="{{thumbnail}}" alt="image" class="searchstax-thumbnail" data-test-id="searchstax-thumbnail">
												{{/thumbnail}}
												<div class="searchstax-search-result-title-container" data-test-id="searchstax-search-result-title-container">
														<h3 class="searchstax-search-result-title" id="title-{{uniqueId}}">{{{title}}}</h3>
												</div>

												{{#paths}}
														<p class="searchstax-search-result-common" tabindex="0" data-test-id="searchstax-search-result-common">
																{{{paths}}}
														</p>
												{{/paths}}

												{{#description}}
														<p tabindex="0" class="searchstax-search-result-description searchstax-search-result-common" data-test-id="searchstax-search-result-description">
																{{{description}}}
														</p>
												{{/description}}

												{{#unmappedFields}}
														{{#isImage}}
																<div class="searchstax-search-result-image-container">
																<img alt="" src="{{value}}" alt="image" class="searchstax-result-image" data-test-id="searchstax-result-image">
																</div>
														{{/isImage}}
														{{^isImage}}
																<p tabindex="0" class="searchstax-search-result-common">
																{{{value}}}
																</p>
														{{/isImage}}
												{{/unmappedFields}}
												{{#distance}}
														<p tabindex="0" class="searchstax-search-result-distance searchstax-search-result-common" data-test-id="searchstax-search-result-distance">
																{{distance}} {{unit}}
														</p>
												{{/distance}}
												</div>
												</a>
									`,
									searchResultUniqueIdAttribute: "data-searchstax-unique-result-id",
								},
								noSearchResultTemplate: {
									template: `
										{{#searchExecuted}}
											<div class="searchstax-no-results-wrap" data-test-id="searchstax-no-results-wrap">
												<div class="searchstax-no-results" data-test-id="searchstax-no-results">
														Showing <strong>no results</strong> for <strong>"{{ searchTerm }}"</strong>
														<br>
														{{#spellingSuggestion}}
																<span>&nbsp;Did you mean <a href="#" aria-label="Did you mean: {{originalQuery}}" class="searchstax-suggestion-term" onclick="searchCallback('{{ spellingSuggestion }}')">{{ spellingSuggestion }}</a>?</span>
														{{/spellingSuggestion}}
												</div>
												<ul class="searchstax-no-results-list" data-test-id="searchstax-no-results-list">
														<li>Try searching for search related terms or topics. We offer a wide variety of content to help you get the information you need.</li>
														<li>Lost? Click on the ‘X” in the Search Box to reset your search.</li>
												</ul>
											</div>
										{{/searchExecuted}}
									`,
								},
							},
							renderMethod: renderConfig.resultsWidget.renderMethod,
						});

						searchstax.addPaginationWidget("searchstax-pagination-container", {
							templates: {
								mainTemplate: {
									template: `
										{{#results.length}}
											<div class="searchstax-pagination-container" data-test-id="searchstax-pagination-container">
												<div class="searchstax-pagination-content">
													<a role="link" class="searchstax-pagination-previous {{#isFirstPage}}disabled{{/isFirstPage}}" aria-disabled="{{#isFirstPage}}true{{/isFirstPage}}{{^isFirstPage}}false{{/isFirstPage}}"  id="searchstax-pagination-previous" data-test-id="searchstax-pagination-previous" tabindex="0" aria-label="Previous Page">< Previous</a>
													<div class="searchstax-pagination-details" data-test-id="searchstax-pagination-details">
														{{startResultIndex}} - {{endResultIndex}} of {{totalResults}}
													</div>
														<a role="link" class="searchstax-pagination-next {{#isLastPage}}disabled{{/isLastPage}}" aria-disabled="{{#isLastPage}}true{{/isLastPage}}{{^isLastPage}}false{{/isLastPage}}" data-test-id="searchstax-pagination-next" id="searchstax-pagination-next" tabindex="0" aria-label="Next Page">Next ></a>
												</div>
											</div>
										{{/results.length}}
									`,
									previousButtonClass: "searchstax-pagination-previous",
									nextButtonClass: "searchstax-pagination-next",
								},
								infiniteScrollTemplate: {
									template: `
										{{#results.length}}
											<div class="searchstax-pagination-container" data-test-id="searchstax-pagination-container">
												<div class="searchstax-pagination-content">
													<a role="link" class="searchstax-pagination-previous {{#isFirstPage}}disabled{{/isFirstPage}}" aria-disabled="{{#isFirstPage}}true{{/isFirstPage}}{{^isFirstPage}}false{{/isFirstPage}}"  id="searchstax-pagination-previous" data-test-id="searchstax-pagination-previous" tabindex="0" aria-label="Previous Page">< Previous</a>
													<div class="searchstax-pagination-details" data-test-id="searchstax-pagination-details">
														{{startResultIndex}} - {{endResultIndex}} of {{totalResults}}
													</div>
														<a role="link" class="searchstax-pagination-next {{#isLastPage}}disabled{{/isLastPage}}" aria-disabled="{{#isLastPage}}true{{/isLastPage}}{{^isLastPage}}false{{/isLastPage}}" data-test-id="searchstax-pagination-next" id="searchstax-pagination-next" tabindex="0" aria-label="Next Page">Next ></a>
												</div>
											</div>
										{{/results.length}}
									`,
									loadMoreButtonClass: "searchstax-pagination-load-more",
								},
							},
						});

						searchstax.addRelatedSearchesWidget("searchstax-related-searches-container", {
							relatedSearchesURL: config.relatedSearchesURL,
							relatedSearchesAPIKey: config.relatedSearchesAPIKey,
							templates: {
								main: {
									template: `
										{{#hasRelatedSearches}}
												<div class="searchstax-related-searches-container" data-test-id="searchstax-related-searches-container" id="searchstax-related-searches-container">
														Related searches: <span id="searchstax-related-searches"></span>
														{{#relatedSearches}}
														<span class="searchstax-related-search">

														</span>
												{{/relatedSearches}}
												</div>
										{{/hasRelatedSearches}}
									`,
									relatedSearchesContainerClass: `searchstax-related-search`,
								},
								relatedSearch: {
									template: `
										<span class="searchstax-related-search searchstax-related-search-item" data-test-id="searchstax-related-search-item" aria-label="Related search: {{related_search}}" tabindex="0">
												{{ related_search }}{{^last}}<span>,</span>{{/last}}
										</span>
									`,
									relatedSearchContainerClass: `searchstax-related-search-item`,
								},
							},
						});

						searchstax.addExternalPromotionsWidget("searchstax-external-promotions-layout-container", {
							templates: {
								mainTemplate: {
									template: `
										{{#hasExternalPromotions}}
												<div class="searchstax-external-promotions-container" id="searchstax-external-promotions-container" data-test-id="searchstax-external-promotions-container">
														 External promotions go here
												</div>
										{{/hasExternalPromotions}}
									`,
									externalPromotionsContainerId: `searchstax-external-promotions-container`,
								},
								externalPromotion: {
									template: `
										<div class="searchstax-external-promotion searchstax-search-result" data-test-id="searchstax-external-promotion">
											<div class="icon-elevated"></div>
											{{#url}}
											<a href="{{url}}" data-searchstax-unique-result-id="{{uniqueId}}" class="searchstax-result-item-link" data-test-id="searchstax-result-item-link"></a>
											{{/url}}
											<div class="searchstax-search-result-title-container">
													<span class="searchstax-search-result-title" data-test-id="searchstax-search-result-title">{{name}}</span>
											</div>
											{{#description}}
											<p class="searchstax-search-result-description searchstax-search-result-common" data-test-id="searchstax-search-result-description">
											{{description}}
											</p>
											{{/description}}
											{{#url}}
											<p class="searchstax-search-result-description searchstax-search-result-common">
											{{url}}
											</p>
											{{/url}}
										</div>
									`,
								},
							},
						});
						const currentViewStyle = localStorage.getItem("viewStyle") || "list";
						const viewStyleElement = document.getElementById("icon-view-style");
						const toggleElement = document.getElementById("toggle-view-style");
						viewStyleElement.classList.add(`icon-${currentViewStyle}`);
						if(currentViewStyle === 'list'){
							toggleElement.setAttribute("aria-label", "View Style Change Button. Current view style is: list");
						}
						else {
							toggleElement.setAttribute("aria-label", "View Style Change Button. Current view style is: grid");
						}
						const searchResultsContainer = document.getElementById("searchstax-results-container");
						searchResultsContainer.classList.add(`searchstax-results-container-${currentViewStyle}`);


						const changeViewStyle = () => {
							const currentViewStyle = localStorage.getItem("viewStyle") || "list";
							const newViewStyle = currentViewStyle === "list" ? "grid" : "list";
							localStorage.setItem("viewStyle", newViewStyle);
							searchResultsContainer.classList.add(`searchstax-results-container-${newViewStyle}`);
							searchResultsContainer.classList.remove(`searchstax-results-container-${currentViewStyle}`);

							viewStyleElement.classList.remove(`icon-${currentViewStyle}`);
							viewStyleElement.classList.add(`icon-${newViewStyle}`);

							const accessibilityElement = document.createElement('span');
							accessibilityElement.setAttribute('id', 'searchAccessibility');

							accessibilityElement.innerHTML =  newViewStyle === 'grid' ? 'View style changed to grid view' : 'View style changed to list view';

							if(newViewStyle === 'list'){
								toggleElement.setAttribute("aria-label", "View Style Change Button. Current view style is: list");
						 	}
						 	else {
								toggleElement.setAttribute("aria-label", "View Style Change Button. Current view style is: grid");
						 	}

							const existingContainerElement = document.getElementById('searchAccessibilityContainer');
							if(existingContainerElement) {
								setTimeout(() => {
									existingContainerElement.appendChild(accessibilityElement);
								}, 100);
							}
						}

						toggleElement.addEventListener('keyup', (event) => {
							if (event.code === 'Space' || event.code === 'Enter') {
								changeViewStyle()
							}
						});

						toggleElement.addEventListener("click", function() {
							changeViewStyle()
						});
					};
					document.head.appendChild(script);
				};
			</script>
		</div>
	<?php
}

get_footer();

?>