<?php

namespace Deployer;

require 'recipe/common.php';

// Config

$configFile = __DIR__ . '/deploy.config.php';

if (!file_exists(__DIR__ . '/deploy.config.php')) {
    die('Missing deploy.config.php – copy deploy.config.example.php and adjust it.');
}

$config = require $configFile;

// Variables

set('bin/php', $config['phpPath']);

set('git_ssh_command', 'ssh');

set('remote_user', $config['remoteUser']);

set('keep_releases', $config['keepReleases']);

set('release_name', $config['releaseName']);

set('wp_theme_slug', $config['wpThemeSlug']);

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
	->set('hostname', $config['serverIP'])
	->set('repository', $config['repository'])
	->set('deploy_path', $config['deployPathStaging']);

host('production')
	->set('stage', 'production')
	->set('hostname', $config['serverIP'])
	->set('repository', $config['repository'])
	->set('deploy_path', $config['deployPathProduction']);

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

	// Clear Nginx site cache
	run('rm -rf /var/cache/nginx/proxy/*');
})->desc('Clear cache');

// Task: Link theme

task('deploy:link-theme', function () {
	if (!test('[ -d {{deploy_path}}/current/theme/{{wp_theme_slug}} ]')) {
		throw new \Exception('Theme folder not found in release');
	}

	run('ln -nfs {{deploy_path}}/current/theme/{{wp_theme_slug}} {{wp_theme_path}}');
})->desc('Link theme');

// Deploy tasks

task('deploy', [
	'deploy:prepare',
	'deploy:version-file',
	'deploy:build-assets',
	'deploy:publish',
]);

// Hooks

after('deploy:publish', 'deploy:wp-cli');
after('deploy:publish', 'deploy:clear-cache');
after('deploy:publish', 'deploy:link-theme');

after('deploy:failed', 'deploy:unlock');
