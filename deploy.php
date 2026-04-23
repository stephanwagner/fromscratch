<?php

namespace Deployer;

require 'recipe/common.php';

// Config

$serverIP = '94.130.121.246';
$phpPath = '/opt/plesk/php/8.4/bin/php';
$repository = 'git@github.com:stephanwagner/fromscratch.git';

set('bin/php', $phpPath);

set('git_ssh_command', 'ssh');

set('remote_user', 'stephanwagner');

set('keep_releases', 3);

set('release_name', date('Y-m-d_H-i-s'));

// Shared files and folders

add('shared_files', [
]);

add('shared_dirs', [
]);

// Writable folders

add('writable_dirs', [
]);

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
    $hasWpCli = test('command -v wp');

    if (!$hasWpCli) {
        writeln('Installing WP-CLI locally...');

        run('mkdir -p ~/bin');
        run('curl -sS https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o ~/bin/wp');
        run('chmod +x ~/bin/wp');
    }
});

// Task: Clear cache

task('deploy:clear-cache', function () use ($phpPath) {
	// Clear WordPress cache
	run('cd {{release_path}} && wp cache flush');

	// Clear Nginx cache
	// TODO run('rm -rf /var/cache/nginx/*');
})->desc('Clear cache');

// Deploy tasks

task('deploy', [
	'deploy:prepare',
	'deploy:version-file',
	'deploy:build-assets',
	'deploy:wp-cli',
	'deploy:clear-cache',
	'deploy:publish',
]);

// Hooks

after('deploy:failed', 'deploy:unlock');
