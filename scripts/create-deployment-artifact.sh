#!/usr/bin/env bash

set -euo pipefail

if [[ "$#" -ne 2 ]]; then
	printf 'Usage: %s <source-directory> <empty-artifact-directory>\n' "$0" >&2
	exit 64
fi

source_dir="$(cd "$1" && pwd -P)"
artifact_dir="$2"

if [[ "$artifact_dir" != /* ]]; then
	echo 'Artifact directory must be an absolute path.' >&2
	exit 64
fi

case "$artifact_dir" in
	"$source_dir"|"$source_dir"/*)
		echo 'Artifact directory must not be inside the source directory.' >&2
		exit 64
		;;
esac

if [[ -e "$artifact_dir" ]]; then
	if [[ ! -d "$artifact_dir" ]] || [[ -n "$(find "$artifact_dir" -mindepth 1 -maxdepth 1 -print -quit)" ]]; then
		echo 'Artifact directory must be empty.' >&2
		exit 64
	fi
else
	mkdir -p "$artifact_dir"
fi

required_source_files=(
	index.php
	themes/epdc-base/functions.php
	themes/epdc-base/style.css
	themes/epdc-base/theme.json
	themes/epdc-base/screenshot.png
	themes/epdc-base/build/index.js
	themes/epdc-base/build/index.asset.php
	themes/epdc-base/build/style-index.css
	themes/epdc-base/build/index.css
)

for required_source_file in "${required_source_files[@]}"; do
	if [[ ! -f "$source_dir/$required_source_file" ]]; then
		echo "Required deployment source file is missing: $required_source_file" >&2
		exit 1
	fi
done

for required_source_directory in plugins themes/epdc-base/parts themes/epdc-base/templates themes/epdc-base/styles themes/epdc-base/build; do
	if [[ ! -d "$source_dir/$required_source_directory" ]]; then
		echo "Required deployment source directory is missing: $required_source_directory" >&2
		exit 1
	fi
done

mkdir -p "$artifact_dir/themes/epdc-base"
cp -a "$source_dir/index.php" "$artifact_dir/"
cp -a "$source_dir/plugins" "$artifact_dir/"

for theme_file in functions.php style.css theme.json screenshot.png; do
	cp -a "$source_dir/themes/epdc-base/$theme_file" "$artifact_dir/themes/epdc-base/"
done

for theme_directory in parts templates styles build; do
	cp -a "$source_dir/themes/epdc-base/$theme_directory" "$artifact_dir/themes/epdc-base/"
done

find "$artifact_dir" -type f \( -name "*.map" -o -name "*.log" -o -name "*.csv" -o -name "*.sql" -o -name "*.bak" -o -name "*.backup" -o -name "*.zip" -o -name "*.tar" -o -name "*.tar.gz" -o -name ".env" -o -name ".env.*" -o -name "auth.json" -o -name "*.pem" -o -name "*.key" -o -name "*.crt" -o -name "*.p12" -o -name "id_rsa" -o -name "id_ed25519" \) -delete
find "$artifact_dir/plugins" -type d \( -name ".git" -o -name ".github" -o -name "node_modules" -o -name "test" -o -name "tests" -o -name "doc" -o -name "docs" \) -prune -exec rm -rf -- {} +

if [[ ! -d "$artifact_dir/plugins/woocommerce" ]]; then
	echo 'Artifact is missing plugins/woocommerce/.' >&2
	exit 1
fi

for required_artifact_file in "${required_source_files[@]}"; do
	if [[ ! -f "$artifact_dir/$required_artifact_file" ]]; then
		echo "Artifact is missing required runtime file: $required_artifact_file" >&2
		exit 1
	fi
done

for forbidden_path in vendor uploads node_modules .git .github .agents .codex .devcontainer src tests upgrade; do
	if [[ -e "$artifact_dir/$forbidden_path" ]]; then
		echo "Artifact contains forbidden path: $forbidden_path" >&2
		exit 1
	fi
done

for forbidden_pattern in '*.map' '*.log' '*.csv' '*.sql' '*.bak' '*.backup' '*.zip' '*.tar' '*.tar.gz' '.env' '.env.*' 'auth.json' '*.pem' '*.key' '*.crt' '*.p12' 'id_rsa' 'id_ed25519'; do
	if find "$artifact_dir" -type f -name "$forbidden_pattern" -print -quit | grep -q .; then
		echo "Artifact contains a forbidden file matching: $forbidden_pattern" >&2
		exit 1
	fi
done

if find "$artifact_dir" -type l -print -quit | grep -q .; then
	echo 'Artifact contains symlinks.' >&2
	exit 1
fi

file_count="$(find "$artifact_dir" -type f | wc -l | tr -d '[:space:]')"
artifact_size="$(du -sh "$artifact_dir" | awk '{print $1}')"
printf 'Artifact validated: %s files, %s.\n' "$file_count" "$artifact_size"
