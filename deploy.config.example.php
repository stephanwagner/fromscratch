<?php
return [
	/**
	 * Server config
	 */

	// The IP address of the server
    'serverIP'   => '',
    
	// The remote ssh user
    'remoteUser' => '',
    
	// The repository URL
    'repository' => '',
	
	// The path to the PHP binary
    'phpPath'    => '/opt/plesk/php/8.4/bin/php',

    /**
     * Theme config
     */
	
	// The WordPress theme folder name
    'wpThemeSlug' => 'fromscratch',

    /**
     * Deploy config
	 * 
	 * The root path to the folder where the repository is deployed to.
     */
	// Production
    'deployPathProduction' => '/var/www/vhosts/fromscratch/httpdocs/fromscratch/production/theme',

	// Staging
    'deployPathStaging' => '/var/www/vhosts/fromscratch/httpdocs/fromscratch/staging/theme',

    /**
     * Release config
     */

	// The number of releases to keep
    'keepReleases' => 3,

	// The release name
    'releaseName' => date('Y-m-d_H-i-s'),
];
