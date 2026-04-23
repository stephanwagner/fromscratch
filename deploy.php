<?php

namespace Deployer;

require 'recipe/common.php';

// Server config

$serverIP = '94.130.121.246';

$remoteUser = 'bytesandstripes';

$repository = 'git@github.com:stephanwagner/fromscratch.git';

$phpPath = '/opt/plesk/php/8.4/bin/php';

// Theme config

$wpThemeSlug = 'fromscratch';

// Deploy config

$pleskDomain = 'bytesandstripes.com';
$pleskFolder = 'fromscratch';

$deployPathProduction = '/var/www/vhosts/' . $pleskDomain . '/httpdocs/' . $pleskFolder . '/production/theme';

$deployPathStaging = '/var/www/vhosts/' . $pleskDomain . '/httpdocs/' . $pleskFolder . '/staging/theme';

// Release config

$keepReleases = 3;

$releaseName = date('Y-m-d_H-i-s');

// Variables

set('bin/php', $phpPath);

set('git_ssh_command', 'ssh');

set('remote_user', $remoteUser);

set('keep_releases', $keepReleases);

set('release_name', $releaseName);

set('wp_theme_slug', $wpThemeSlug);

set('wp_path', '{{deploy_path}}/../wordpress');

set('wp_cli_path', '{{deploy_path}}/shared/wp');

set('wp_theme_path', '{{wp_path}}/wp-content/themes/{{wp_theme_slug}}');

// Shared files and folders

add('shared_files', []);

add('shared_dirs', []);

// Writable folders

add('writable_dirs', []);

// Hosts

host('staging')
	->set('stage', 'staging')
	->set('hostname', $serverIP)
	->set('repository', $repository)
	->set('deploy_path', $deployPathStaging);

host('production')
	->set('stage', 'production')
	->set('hostname', $serverIP)
	->set('repository', $repository)
	->set('deploy_path', $deployPathProduction);

// Task: Remove executables

task('deploy:remove-executables', function () {
	run('cd {{release_path}} && rm -fr compile_po.sh db.sh');
})->desc('Remove executables');

// Task: Create deploy version file

task('deploy:version-file', function () {
	run('echo "' . md5(time()) . '" > {{release_path}}/.deploy-version');
})->desc('Create deploy version file');

// Task: Build assets

task('deploy:build-assets', function () {
	if (has('previous_release')) {
		run('
			if [ -d {{previous_release}}/node_modules ]; then
				cp -R {{previous_release}}/node_modules {{release_path}}/node_modules
			fi
		');
	}
	run('cd {{release_path}} && npm install && npm run build');
})->desc('Build assets');

// Task: Ensure WP CLI is installed

task('deploy:wp-cli', function () {
	if (!test('[ -f {{wp_cli_path}} ]')) {
		writeln('Installing WP-CLI...');

		run('mkdir -p $(dirname {{wp_cli_path}})');
		run('curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o {{wp_cli_path}}');
		run('chmod +x {{wp_cli_path}}');
	}

	run('{{wp_cli_path}} cli update');
})->desc('Ensure WP CLI is installed');

// Task: Clear cache

task('deploy:clear-cache', function () {
	// Clear WordPress cache
	if (test('{{wp_cli_path}} core is-installed --path={{wp_path}}')) {
		run('{{wp_cli_path}} cache flush --path={{wp_path}} || true');
	}

	// Clear Nginx cache
	// TODO run('rm -rf /var/cache/nginx/*');
})->desc('Clear cache');

// Task: Link theme

task('deploy:link-theme', function () {
	if (!test('[ -d {{deploy_path}}/current/themes/{{wp_theme_slug}} ]')) {
		throw new \Exception('Theme folder not found in release');
	}

	run('ln -nfs {{deploy_path}}/current/theme/{{wp_theme_slug}} {{wp_theme_path}}');
})->desc('Link theme');

// Deploy tasks

task('deploy', [
	'deploy:prepare',
	'deploy:remove-executables',
	'deploy:version-file',
	'deploy:build-assets',
	'deploy:publish',
]);

// Hooks
after('deploy:publish', 'deploy:wp-cli');
after('deploy:publish', 'deploy:clear-cache');
after('deploy:publish', 'deploy:link-theme');

after('deploy:failed', 'deploy:unlock');

// TODO

// // Task: WP install

// task('wp:install', function () {
// 	// 1. Safety: abort if WP already exists
// 	if (test('{{wp_cli_path}} core is-installed --path={{wp_path}}')) {
// 		throw new \Exception('WP already installed');
// 	}

// 	if (test('[ -f {{wp_path}}/wp-load.php ]')) {
// 		throw new \Exception('WP core already present');
// 	}

// 	// 2. Ask for confirmation
// 	$confirm = ask('This will install WordPress. Type "yes" to continue');

// 	if ($confirm !== 'yes') {
// 		writeln('Aborted.');
// 		return;
// 	}

// 	// 3. Install WP
// 	writeln('Downloading WordPress...');
// 	writeln('{{bin/php}} {{wp_cli_path}} core download --path={{wp_path}}');
// 	run('{{bin/php}} {{wp_cli_path}} core download --path={{wp_path}}');

// 	// TODO we could automate the theme install here

// 	writeln('<info>WordPress installed successfully.</info>');
// });
