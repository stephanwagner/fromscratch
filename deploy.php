<?php

namespace Deployer;

require 'recipe/common.php';

// Config

$serverIP = '94.130.121.246';
$phpPath = '/opt/plesk/php/8.4/bin/php';
$repository = 'git@github.com:stephanwagner/fromscratch.git';
$wpThemeSlug = 'fromscratch';

set('bin/php', $phpPath);

set('git_ssh_command', 'ssh');

set('remote_user', 'bytesandstripes');

set('keep_releases', 3);

set('release_name', date('Y-m-d_H-i-s'));

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
	->set('deploy_path', '~/httpdocs/fromscratch/staging/theme');

host('production')
	->set('stage', 'production')
	->set('hostname', $serverIP)
	->set('repository', $repository)
	->set('deploy_path', '~/httpdocs/fromscratch/production/theme');

// Remove executables

task('deploy:remove-executables', function () {
	run('cd {{release_path}} && rm -fr compile_po.sh db.sh');
})->desc('Remove executables');

// Task: Deploy version file

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

// Task ensure WP CLI is installed

task('deploy:wp-cli', function () {
    if (!test('[ -f {{wp_cli_path}} ]')) {
        writeln('Installing WP-CLI...');

        run('mkdir -p $(dirname {{wp_cli_path}})');
        run('curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o {{wp_cli_path}}');
        run('chmod +x {{wp_cli_path}}');
    }

    run('{{wp_cli_path}} cli update');
});

// Task: Clear cache

task('deploy:clear-cache', function () use ($phpPath) {
	// Clear WordPress cache
	// TODO run('{{wp_cli_path}} cache flush --path={{wp_path}}');

	// Clear Nginx cache
	// TODO run('rm -rf /var/cache/nginx/*');
})->desc('Clear cache');

// Task: Link theme

task('deploy:link-theme', function () {
    run('ln -nfs {{deploy_path}}/current/theme/{{wp_theme_slug}} {{wp_theme_path}}');
})->desc('Link theme');

// Deploy tasks

task('deploy', [
	'deploy:prepare',
	'deploy:remove-executables',
	'deploy:version-file',
	'deploy:build-assets',
	'deploy:wp-cli',
	'deploy:clear-cache',
	'deploy:publish',
]);

// Hooks

after('deploy:publish', 'deploy:link-theme');
after('deploy:failed', 'deploy:unlock');
