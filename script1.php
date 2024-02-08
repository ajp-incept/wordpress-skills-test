<?php

function prefix_select_arg_validate_callback( $value, $request, $param ) {
    if ( ! is_string( $value ) ) {
        return new WP_Error( 'rest_invalid_param', esc_html__( 'The filter argument must be a string.', 'my-text-domain' ), array( 'status' => 400 ) );
    }

    $attributes = $request->get_attributes();
    $args = $attributes['args'][ $param ];

    if (! in_array( $value, $args['enum'], true ))
        return new WP_Error( 'rest_invalid_param', sprintf( __( '%s is not one of %s' ), $param, implode( ', ', $args['enum'] ) ), array( 'status' => 400 ) );
}

function get_x_modules($companyid, $filters=null) {
	global $wpdb;
  $departmentid = ($filters!==null && is_numeric($filters->departmentid)) ? (int)$filters->departmentid : "";
  $department_filter = ($departmentid !== "") ? "AND company_users.department_id = ".$departmentid." ":"";

  $number_modules_company = get_modules_company($companyid);

  $results = $wpdb->get_results(
      $wpdb->prepare(
          "SELECT COUNT(*) AS count, guides_completed.userid
          FROM guides_completed, company_users
          WHERE company_users.company_id = %d
          AND company_users.userid = guides_completed.userid
          AND (company_users.role = 'Employee' OR company_users.role = 'Manager')
          ".$department_filter."
          GROUP BY guides_completed.userid
          HAVING COUNT(*) >= %d",
          $companyid,$number_modules_company
      )
  );

	return count($results);
}

function rqis_analyticsinfo($data)
{
	$parameters = $data->get_params();
  $type_of_info = $data['select'];
	$departmentid= $parameters['department'];
	$filters = new \stdClass();
	$filters->departmentid = $departmentid;
	
    if ($type_of_info == 'dashboard') {
        //ignore for now
        return json_encode(new \stdClass());
    } else if ($type_of_info == 'completionLog') {    
        $companyid = getcompany(get_current_user_id());
        $analytics = new \stdClass();
        $analytics->completedusers = get_x_modules($companyid, $filters);
        $analytics->pcompleted = get_percentage($analytics->completedusers, $analytics->allusers );
    
        return  $analytics;
    }	
}
add_action('rest_api_init', function () {
	register_rest_route('dummy-api/v1', '/company/select=(?P<select>[a-zA-Z]+)', array(
		'methods' => 'GET',
		'callback' => 'rqis_analyticsinfo',
		'summary' => 'Returns statistics for analytics',
		'description' => ' ',
		'args' => array(
			'select' => array('enum' => array('dashboard','completionLog'),
			'validate_callback' => 'prefix_select_arg_validate_callback'),
		),
		'permission_callback' => function ($request) {
			if (current_user_can('subscriber'))
				return true;
		}
	));
});
