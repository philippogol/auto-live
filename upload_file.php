<?php
// Load configuration
$config = include 'config.php';

// Retrieve the GitHub token
$token = $config['github_token'];
if (!$token) {
    die("Error: GitHub token not found in config.php.\n");
}

$repoOwner = "philippogol";
$repoName = "auto-live";
$branch = "main";

// Directory containing files to upload
$folderPath = "/applications/mamp/htdocs/auto-live";
if (!is_dir($folderPath)) {
    die("Error: Folder $folderPath does not exist.\n");
}

// Loop through all files in the folder
$files = scandir($folderPath);
foreach ($files as $file) {
    // Skip directories
    if ($file === '.' || $file === '..') {
        continue;
    }

    $filePath = "$folderPath/$file";
    if (!is_file($filePath)) {
        continue;
    }

    $fileName = basename($filePath);
    $fileContent = base64_encode(file_get_contents($filePath));

    // GitHub API URL to fetch file metadata
    $fileMetaUrl = "https://api.github.com/repos/$repoOwner/$repoName/contents/$fileName";

    // Step 1: Get the current `sha` and content of the file (if it exists)
    $ch = curl_init($fileMetaUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "User-Agent: PHP Script"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    if ($response === false) {
        die('cURL Error: ' . curl_error($ch) . "\n");
    }

    curl_close($ch);

    $fileMetaData = json_decode($response, true);
    $sha = isset($fileMetaData['sha']) ? $fileMetaData['sha'] : null;

    // Step 2: Check if the content is unchanged
    if ($sha && isset($fileMetaData['content'])) {
        $currentContent = trim(base64_decode($fileMetaData['content']));
        $newContent = trim(file_get_contents($filePath));

        if ($currentContent === $newContent) {
            echo "No changes detected for $fileName. Skipping.\n";
            continue;
        }
    }

    // Step 3: Upload or Update the File
    $uploadUrl = "https://api.github.com/repos/$repoOwner/$repoName/contents/$fileName";

    $data = [
        "message" => $sha ? "Update $fileName via PHP API" : "Create $fileName via PHP API",
        "content" => $fileContent,
        "branch"  => $branch
    ];

    // Include `sha` if this is an update
    if ($sha) {
        $data['sha'] = $sha;
    }

    $ch = curl_init($uploadUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "User-Agent: PHP Script",
    ]);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);

    if ($response === false) {
        echo "cURL Error for $fileName: " . curl_error($ch) . "\n";
        continue;
    }

    curl_close($ch);

    $responseData = json_decode($response, true);
    if (isset($responseData['content'])) {
        echo "Success! File committed: " . $responseData['content']['path'] . "\n";
    } else {
        echo "Error for $fileName: " . $response . "\n";
    }
}
