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
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['photo']['tmp_name'];
        $fileName = $_FILES['photo']['name'];
        $filePath = __DIR__ . '/resources/' . $fileName;

        // Move the uploaded file to the resources directory
        if (move_uploaded_file($fileTmpPath, $filePath)) {
            try {
                $bucketName = 'tenakata_admission_candidates';
                $result = uploadFileToGCS($bucketName, $filePath, $fileName);
                
                if ($result) {
                    $photoUrl = $result['mediaLink'];

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
                        'PhotoURL' => $photoUrl,
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

function uploadFileToGCS($bucketName, $filePath, $fileName) {
    // Create a Cloud Storage client
    $storage = new StorageClient([
        'keyFilePath' => __DIR__ . '/Resource/sonic-harbor-307016-ba7a47ea0912'
    ]);



    // Upload the file
    // Authenticate using the service account key
    $client = new Client();
    $credentials = ApplicationDefaultCredentials::getCredentials(
        'https://www.googleapis.com/auth/cloud-platform'
    );

    // Create a signed URL for uploading the file
    $url = "https://storage.googleapis.com/upload/storage/v1/b/{$bucketName}/o?uploadType=media&name={$fileName}";
    
    // Open the file
    $fileData = file_get_contents($filePath);

    try {
        // Send the file data to the signed URL
        $response = $client->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $credentials->fetchAuthToken()['access_token'],
                'Content-Type' => mime_content_type($filePath),
                'Content-Length' => strlen($fileData)
            ],
            'body' => $fileData
        ]);

        if ($response->getStatusCode() === 200) {
            // Parse the response
            $responseData = json_decode($response->getBody(), true);
            return $responseData;
        } else {
            throw new Exception('Failed to upload file to Google Cloud Storage');
        }
    } catch (Exception $e) {
        error_log('File upload error: ' . $e->getMessage());
        return false;
    }

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
