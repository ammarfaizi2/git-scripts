<?php

$i = 0;
$handle = fopen(__DIR__."/linux.tree.lock", "ab");
while (!flock($handle, LOCK_EX | LOCK_NB)) {
	if ($i >= 10) {
		fclose($handle);
		printf("Cannot acquire the lock in 10 seconds, exiting...\n");
		exit(0);
	}
	printf("[%d] Got contention, sleeping for a sec...\n", $i++);
	sleep(1);
}

chdir(__DIR__."/linux.tree");
$git = trim(shell_exec("which git"));
$ENV = $_SERVER;
unset($ENV["argv"], $ENV["argc"]);

$cmd = "{$git} pull 2>&1";
printf("Executing: %s\n", $cmd);
echo shell_exec($cmd);

$cmd = "{$git} fetch --all --prune --jobs=32 2>&1";
printf("Executing: %s\n", $cmd);
echo shell_exec($cmd);

$tmp = trim(shell_exec("{$git} branch --remotes --list | grep -P '^\s*\@(?=[^@])(?!github.com).*?/.+$'"));
$tmp = explode("\n", $tmp);
$remote_branches = [];
foreach ($tmp as &$branch) {
	$branch = trim($branch);
	if ($branch[0] !== '@') {
		printf("Invalid branch name {$branch}!\n");
		exit(0);
	}
	$branch = substr(trim($branch), 1);
	$remote_branches[$branch] = true;
}
unset($branch);

$tmp = trim(shell_exec("{$git} branch --list"));
$tmp = explode("\n", $tmp);
$active_branches = [];
foreach ($tmp as &$branch) {
	if (strlen($branch) < 3)
		continue;

	$branch = trim($branch);

	/*
	 * Current working branch has a star.
	 */
	if ($branch[0] === '*' && $branch[1] === ' ')
		$branch = trim(substr($branch, 2));

	$active_branches[$branch] = true;
}
unset($branch);

/*
 * Find any remote branches that are not in active branches.
 * Then make them become an active branch.
 */
$new_branches = [];
foreach ($remote_branches as $branch => $v) {
	if (isset($active_branches[$branch]))
		continue;

	$new_branches[$branch] = true;
}

printf("=========================================================\n");
printf("Number of new branches    = %d\n", count($new_branches));
printf("Number of active branches = %d\n", count($active_branches));
printf("Number of remote branches = %d\n", count($remote_branches));
printf("=========================================================\n");

foreach ($new_branches as $branch => $v) {
	$upstream = "@".$branch;
	$branch = $branch;

	$eupstream = escapeshellarg($upstream);
	$ebranch = escapeshellarg($branch);
	$cmd = "{$git} branch --track {$ebranch} {$eupstream} 2>&1";
	printf("Executing: %s\n", $cmd);
	echo shell_exec($cmd);

	$head        = ".git/refs/heads/{$branch}";
	$remote_head = realpath(".git/refs/remotes/{$upstream}");
	if (!$remote_head)
		continue;

	if (!file_exists($head)) {
		echo shell_exec("mkdir -pv ".escapeshellarg($head));
		rmdir($head);
	}

	@unlink($head);
	symlink($remote_head, $head);
}

if (in_array("--force-link", $argv)) {
	foreach ($remote_branches as $branch => $v) {
		$upstream = "@".$branch;
		$branch = $branch;

		$head        = ".git/refs/heads/{$branch}";
		$remote_head = realpath(".git/refs/remotes/{$upstream}");
		if (!$remote_head)
			continue;

		if (!file_exists($head)) {
			echo shell_exec("mkdir -pv ".escapeshellarg($head));
			rmdir($head);
		}

		@unlink($head);
		symlink($remote_head, $head);
	}
}

echo shell_exec("{$git} push -f --all  @@ammarfaizi2/linux-block 2>&1");
echo shell_exec("{$git} push -f --tags @@ammarfaizi2/linux-block 2>&1");
echo shell_exec("{$git} push -f --all  @@ammarfaizi2/linux-fork 2>&1");
echo shell_exec("{$git} push -f --tags @@ammarfaizi2/linux-fork 2>&1");
flock($handle, LOCK_UN);
fclose($handle);
