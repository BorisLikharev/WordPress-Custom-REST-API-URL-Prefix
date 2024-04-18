<?php
/*
	Plugin Name: Custom REST API URL Prefix
	Description: This plugin allows administrators to change the base URL prefix of the WordPress REST API, facilitating simple and failproof customization for security or branding purposes.
	Version: 1.0
	Author: Boris Likharev
	Author URI: mailto:boris@boris.la
*/

if (!defined("ABSPATH")) {
    die();
} // ;-)

// Adding our submenu item under Settings
function BL_Custom_REST_API_URL_AddMenuItems()
{
    add_submenu_page(
        "options-general.php", // The parent slug: using options-general.php
        "Custom REST API URL Prefix", // Page title
        "REST API URL Prefix", // Menu title
        "manage_options", // Only allow administrators by default; 'manage_options' should be sufficient
        "custom-restapi-url-prefix", // Menu slug
        "BL_Custom_REST_API_URL_OptionsPage" // Function that outputs the content of your options page
    );
}

add_action("admin_menu", "BL_Custom_REST_API_URL_AddMenuItems");

// This function contains everything related to the options page of the plugin
function BL_Custom_REST_API_URL_OptionsPage()
{
    // Check user capabilities
    if (!current_user_can("manage_options")) {
        return;
    }

    // Showing the beginning of the options page
    echo '<div class="wrap">';
    echo "<h1>Custom REST API URL Prefix</h1>";

    $home_url = get_home_url(); // Gets the home URL of the WordPress installation
    $current_prefix = get_option("BL_Custom_REST_API_URL_StoredPrefix", ""); // Gets the stored prefix

    // Properly handle options form submission
    if (
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        check_admin_referer(
            "BL_Custom_REST_API_URL_Action",
            "BL_Custom_REST_API_URL_Nonce"
        ) &&
        !empty($_POST["customized_restapi_url_prefix"])
    ) {
        $raw_prefix = filter_input(
            INPUT_POST,
            "customized_restapi_url_prefix",
            FILTER_SANITIZE_STRING
        );
        $current_prefix = sanitize_key($raw_prefix); // Sanitize the input
        $current_prefix = strtolower($current_prefix); // Convert to lowercase
        update_option("BL_Custom_REST_API_URL_StoredPrefix", $current_prefix); // Save the sanitized input
        $data_saved = true; // Flag to indicate that the new prefix has been saved
    } else {
        $data_saved = false; // Set flag to false if the new prefix has not been posted
    }

    // Display success notice if the new prefix has been saved
    if ($data_saved) {
        echo '<div class="notice notice-success is-dismissible">
        <p><b>New REST API URL prefix \'' .
            esc_html($current_prefix) .
            '\' has been saved.</b> Now, refresh the rewrite rules by going to <a href="' .
            esc_url(admin_url("options-permalink.php")) .
            '">Settings -> Permalinks</a> and clicking <b>\'Save Changes\'</b>, and then <b>clean your object cache</b>.</p>
        </div>';
    }
    // Showing the option form in HTML
    ?>

<form method="post" action="">
	<?php wp_nonce_field("BL_Custom_REST_API_URL_Action", "BL_Custom_REST_API_URL_Nonce"); ?>

	<p>This plugin allows you to easily modify the REST API URL Prefix in your WordPress installation. Typically, you only need to do it once—set it and forget it. <br>Therefore, the field below is locked to prevent accidental modifications. </p>
	<p>However, if you are certain that you want to change your REST API URL prefix, <a href="#" id="form_enable_editing">click here to enable editing.</a></p>
	<p><b>IMPORTANT NOTE:</b> Please make sure to keep this plugin active at all times to preserve this customization.</p>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row"><label for="default_category">REST API URL Prefix</label></th>
				<td>
					<input type="text" id="customized_restapi_url_prefix" name="customized_restapi_url_prefix" pattern="[a-z0-9_-]*" value="<?php echo esc_attr($current_prefix); ?>" disabled />
					<p class="description">The REST API root <span id="newprefix_phrase"> is </span>
						<code class="non-default-example" style="">
							<span id="newprefix_preview"><?php echo esc_url($home_url) ."/" . esc_html($current_prefix); ?>/</span>
						</code>
					</p>
				</td>
			</tr>
		</tbody>
	</table>

	<p class="submit"><input type="submit" name="submit" id="submit" style="display: none;" class="button button-primary" value="Save Changes" disabled></p>

	<script>
		document.getElementById("form_enable_editing").addEventListener("click", function(event) {
			event.preventDefault(); // Prevent the default link behavior
			document.getElementById("customized_restapi_url_prefix").disabled = false; // Enable the input field
			document.getElementById("submit").style.display = 'inline-block'; // Show the submit button
			document.getElementById("submit").disabled = false; // Enable submit button
		});

		// Function to normalize the input
		function normalizeInput(input) {
			// Replace spaces with hyphens
			var normalized = input.replace(/\s+/g, '-');

			// Remove non-alphanumeric characters and diacritics (just in case, who knows)
			normalized = normalized.normalize("NFD").replace(/[\u0300-\u036f]/g, "").replace(/[^a-z0-9-_]/ig, "");

			// Convert to lowercase
			normalized = normalized.toLowerCase();

			return normalized;
		}

		// Validating input
		function validateInput(event) {
			var input = event.target.value;

			// Normalize the input
			var normalizedInput = normalizeInput(input);

			// Update the input value with the normalized one
			event.target.value = normalizedInput;
		}

		// Add event listener to the input field
		document.getElementById("customized_restapi_url_prefix").addEventListener("input", function(event) {
			validateInput(event);

			var basePath = "<?php echo esc_js($home_url); ?>/";
			var currentValue = event.target.value.trim(); // Trim the input value to remove leading and trailing whitespace
			var dynamicContent;

			if (currentValue === '') {
				dynamicContent = "wp-json";
				document.getElementById("newprefix_phrase").textContent = ' cannot be empty! Just a reminder, the default is ';
				document.getElementById("newprefix_preview").textContent = dynamicContent.toLowerCase();
				document.getElementById("submit").disabled = true; // Disable submit button if input is empty
			} else {
				dynamicContent = basePath + currentValue + "/";
				document.getElementById("newprefix_phrase").textContent = ' would be ';
				document.getElementById("newprefix_preview").textContent = dynamicContent.toLowerCase();
				document.getElementById("submit").disabled = false; // Enable submit button if input is not empty
			}
		});
	</script>

</form>

<?php echo "</div>"; // end of "wrap" div
} // End of BL_Custom_REST_API_URL_OptionsPage()

// The most important piece of code, lol. Filters the REST API URL prefix.
add_filter("rest_url_prefix", function () {
    // Define the default prefix directly in the function
    $default_prefix = "wp-json";

    // Check for a cached value to minimize database reads
    $cached_prefix = wp_cache_get(
        "BL_Custom_REST_API_URL_CachedPrefix",
        "options"
    );
    if ($cached_prefix !== false) {
        return $cached_prefix;
    }

    // Fetch the custom REST prefix from the options table or use default if not set
    $customPrefix = get_option("BL_Custom_REST_API_URL_StoredPrefix");

    // If no custom prefix is set or it's exactly the same as the default, use and cache the default prefix
    if (empty($customPrefix) || $customPrefix === $default_prefix) {
        wp_cache_set(
            "BL_Custom_REST_API_URL_CachedPrefix",
            $default_prefix,
            "options"
        );
        return $default_prefix;
    }

    // Cache the valid custom prefix to reduce future database reads
    wp_cache_set(
        "BL_Custom_REST_API_URL_CachedPrefix",
        $customPrefix,
        "options"
    );

    // Return the valid custom prefix
    return $customPrefix;
});

function BL_Custom_REST_API_URL_Activation()
{
    // Get the current REST URL prefix
    $default_prefix = rest_get_url_prefix();

    // Save the current prefix in the options table
    update_option("BL_Custom_REST_API_URL_StoredPrefix", $default_prefix);
}

// Register the activation hook
register_activation_hook(__FILE__, "BL_Custom_REST_API_URL_Activation");

// Cleanup function
function BL_Custom_REST_API_URL_Cleanup()
{
    // Delete stored custom REST API prefix option
    delete_option("BL_Custom_REST_API_URL_StoredPrefix");

    // Delete custom cache associated with REST API prefix
    wp_cache_delete("BL_Custom_REST_API_URL_CachedPrefix", "options");
}

// Clean up the database when the plugin is deleted
register_uninstall_hook(__FILE__, "BL_Custom_REST_API_URL_Cleanup");
