<?php
// Allow from any origin
header("Access-Control-Allow-Origin: *");

// Allow specific HTTP methods
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE, PUT");

// Allow specific headers
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Check if it's a preflight request and return the headers
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

include 'config.php';

require 'vendor/autoload.php';

use Google\Cloud\Storage\StorageClient;
use Google\Auth\ApplicationDefaultCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


        // Ensure gpsLocation is set
        // if (!isset($_POST['gpsLocation'])) {
        //     echo json_encode(['success' => false, 'message' => 'GPS Location is missing.']);
        //     exit;
        // }

    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['photo']['tmp_name'];
        $fileName = $_FILES['photo']['name'];
        $filePath = __DIR__ . '/Resource/' . $fileName;
        
        // Move the uploaded file to the resources directory
        if (move_uploaded_file($fileTmpPath, $filePath)) {
            try {
                $bucketName = 'tenakata_admission_candidates';
                $result = upload_object($bucketName, $fileName, $filePath);
                
                if ($result) {
                    $photoUrl = sprintf('https://storage.googleapis.com/%s/%s', $bucketName, $fileName);

                    // Collect form data
                    $formData = [
                        'Name' => $_POST['firstName'] . ' ' . $_POST['lastName'],
                        'Email' => $_POST['email'],
                        'Gender' => $_POST['gender'],
                        'Age' => $_POST['age'],
                        'MaritalStatus' => $_POST['maritalStatus'],
                        'PasswordHash' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                        'GPSLocation' => $_POST['gpsLocation'],
                        'Country' => $_POST['country'],
                        'IQScore' => $_POST['iq'],
                        'Height' => $_POST['height'],
                        'PhotoURL' => $fileName,
                        'Ranking' => 0, // Default value
                        'ScorePoints' => 0 // Default value
                    ];

                    $candidateId = saveCandidateData($formData);

                    if ($candidateId) {
                        echo json_encode(['success' => true, 'candidateId' => $candidateId, 'fileUrl' => $photoUrl]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save candidate data']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to upload file to Google Cloud Storage']);
                }
            } catch (Exception $e) {
                error_log("File upload error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            }
        } else {
            error_log("Failed to move uploaded file");
            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file']);
        }
    } else {
        error_log("No file uploaded or there was an upload error");
        echo json_encode(['success' => false, 'message' => 'No file uploaded or there was an upload error']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}

/**
 * Upload a file.
 *
 * @param string $bucketName The name of your Cloud Storage bucket.
 * @param string $objectName The name of your Cloud Storage object.
 * @param string $source The path to the file to upload.
 * @return bool true if upload was successful, false otherwise
 */
function upload_object(string $bucketName, string $objectName, string $source): bool
{
    $storage = new StorageClient([
        'keyFilePath' => __DIR__ .'/Resource/sonic-harbor-307016-745c48b1b82e.json',
    ]);
    
    if (!$file = fopen($source, 'r')) {
        throw new \InvalidArgumentException('Unable to open file for reading');
    }
    
    $bucket = $storage->bucket($bucketName);
    $object = $bucket->upload($file, [
        'name' => $objectName,
        // 'predefinedAcl' => 'publicRead'
    ]);

    
    // printf('Uploaded %s to gs://%s/%s' . PHP_EOL, basename($source), $bucketName, $objectName);
    return true;
}

function debug($message) {
    error_log($message);
}

function saveCandidateData($formData) {
    $servername = DB_HOST;
    $username = DB_USERNAME;
    $password = DB_PASSWORD;
    $dbname = DB_NAME;

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        debug("Connection failed: " . $conn->connect_error);
        return false;
    }

    $stmt = $conn->prepare("INSERT INTO candidates (Name, Email, Gender, Age, MaritalStatus, PasswordHash, GPSLocation, Country, IQScore, Height, PhotoURL, Ranking, ScorePoints) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssiisssiiiii", $formData['Name'], $formData['Email'], $formData['Gender'], $formData['Age'], $formData['MaritalStatus'], $formData['PasswordHash'], $formData['GPSLocation'], $formData['Country'], $formData['IQScore'], $formData['Height'], $formData['PhotoURL'], $formData['Ranking'], $formData['ScorePoints']);

    if ($stmt->execute()) {
        $last_id = $stmt->insert_id;
        $stmt->close();
        $conn->close();
        return $last_id;
    } else {
        debug("Error: " . $stmt->error);
        $stmt->close();
        $conn->close();
        return false;
    }
}
