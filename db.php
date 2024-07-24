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
use Dompdf\Dompdf;
use Dompdf\Options;

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
                        $formData['CandidateId'] = $candidateId;
                        $formData['ScorePoints'] = calculateEligibility($formData);
                        updateCandidateRanking($candidateId, $formData['ScorePoints']);
                        $candidates = updateAllCandidateRankings(); // Recalculate rankings for all candidates

                        $scoreCardData= generateScoreCardData($formData);
                        $pdfUrl = generatePdf($scoreCardData, $candidates);

                        echo json_encode([
                            'success' => true,
                            'candidateId' => $candidateId,
                            'fileUrl' => $photoUrl,
                            'scoreCardHtml' => $scoreCardData,
                            'pdfUrl' => $pdfUrl
                        ]);
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

function generateScoreCardData($formData) {
    // Extract first and last names from the full name
    $nameParts = explode(' ', $formData['Name']);
    $firstName = $nameParts[0];
    $lastName = isset($nameParts[1]) ? $nameParts[1] : '';

    $userData = [
        'firstName' => $firstName,
        'lastName' => $lastName,
        'email' => $formData['Email'],
        'gender' => $formData['Gender'],
        'age' => $formData['Age'],
        'maritalStatus' => $formData['MaritalStatus'],
        'gpsLocation' => $formData['GPSLocation'],
        'country' => $formData['Country'],
        'iqScore' => $formData['IQScore'],
        'height' => $formData['Height'],
        'ranking' => $formData['Ranking'],
        'scorePoints' => $formData['ScorePoints'],
        'photoUrl' => $formData['PhotoURL']
    ];

    return $userData;
}



function calculateEligibility($formData) {
    $score = 0;

    // Gender criteria
    if ($formData['Gender'] == 'Female') {
        $score += 56.5;
    }

    // Age criteria
    if ($formData['Age'] > 43) {
        $score += 20; // Double the chances
    } elseif ($formData['Age'] < 26) {
        $score -= 20; // Reduce chances
    } else {
        // Proportional points for age between 26 and 43
        $age = $formData['Age'];
        $agePoints = (($age - 26) / (43 - 26)) * 20;
        $score += $agePoints;
    }

    // IQ criteria
    $iq = $formData['IQScore'];
    if ($iq > 100) {
        // Proportional points for IQ above 100, capped at 50 points
        $iqPoints = min(($iq - 100) * 0.5, 50);
        $score += $iqPoints;
    } else {
        // Proportional deduction for IQ below 100, capped at 50 points
        $iqPoints = min((100 - $iq) * 0.5, 50);
        $score -= $iqPoints;
    }

    // Country criteria
    if (strtolower($formData['Country']) != 'kenya') {
        $score -= 100; // Not eligible
    }

    // Ensure score is within 0-100 range
    if ($score > 100) {
        $score = 100;
    } elseif ($score < 0) {
        $score = 0;
    }

    return $score;
}

function updateAllCandidateRankings() {
    $servername = DB_HOST;
    $username = DB_USERNAME;
    $password = DB_PASSWORD;
    $dbname = DB_NAME;

    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);

    // Check connection
    if ($conn->connect_error) {
        debug("Connection failed: " . $conn->connect_error);
        return [];
    }

    // Retrieve all candidates
    $result = $conn->query("SELECT * FROM candidates");
    if ($result->num_rows > 0) {
        $candidates = [];
        while ($row = $result->fetch_assoc()) {
            $candidates[] = $row;
        }

        // Calculate scores for all candidates
        foreach ($candidates as &$candidate) {
            $candidate['ScorePoints'] = calculateEligibility($candidate);
        }

        // Sort candidates by scores in descending order
        usort($candidates, function ($a, $b) {
            return $b['ScorePoints'] <=> $a['ScorePoints'];
        });

        // Assign rankings
        foreach ($candidates as $index => $candidate) {
            $ranking = $index + 1;
            $stmt = $conn->prepare("UPDATE candidates SET Ranking = ? WHERE id = ?");
            $stmt->bind_param("ii", $ranking, $candidate['id']);
            $stmt->execute();
        }

        $stmt->close();
        $conn->close();
        return $candidates;
    } else {
        debug("No candidates found.");
        $conn->close();
        return [];
    }
}



function generatePdf($scoreCardHtml, $candidates) {
    $options = new Options();
    $options->set('defaultFont', 'Arial');

    $dompdf = new Dompdf($options);

    // Sort candidates by ScorePoints in descending order
    usort($candidates, function ($a, $b) {
        return $b['ScorePoints'] <=> $a['ScorePoints'];
    });

    $html = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { width: 80%; margin: 0 auto; }
            .header { text-align: center; margin-bottom: 20px; }
            .header img { width: 100px; }
            .candidate-list { margin-top: 30px; }
            .candidate-list table { width: 100%; border-collapse: collapse; }
            .candidate-list th, .candidate-list td { border: 1px solid #000; padding: 8px; text-align: left; }
            .candidate-list th { background-color: #f2f2f2; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Tenakata University Admission Candidates</h1>
            </div>
            <div class='candidate-list'>
                <h3>List of Candidates</h3>
                <table>
                    <tr>
                        <th>Ranking</th>
                        <th>Name</th>
                        <th>Country</th>
                        <th>Score</th>
                    </tr>";

    $ranking = 1;
    foreach ($candidates as $candidate) {
        $html .= "
                    <tr>
                        <td>{$ranking}</td>
                        <td>{$candidate['Name']}</td>
                        <td>{$candidate['Country']}</td>
                        <td>{$candidate['ScorePoints']}</td>
                    </tr>";
        $ranking++;
    }

    $html .= "
                </table>
            </div>

        </div>
    </body>
    </html>
    ";

    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    $formData['CandidateId'] = rand(1, 1000000); // Generates a random number between 1 and 1,000,000

    $output = $dompdf->output();
    $pdfFilePath = __DIR__ . "/Resource/scorecards/scorecard_{$formData['CandidateId']}.pdf";
    file_put_contents($pdfFilePath, $output);

    // Upload PDF to Google Cloud Storage
    $bucketName = 'tenakata_admission_candidates';
    $fileName = "scorecard_{$formData['CandidateId']}.pdf";
    upload_object($bucketName, $fileName, $pdfFilePath);

    return sprintf('https://storage.googleapis.com/%s/%s', $bucketName, $fileName);
}



function updateCandidateRanking($candidateId,  $score) {
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

    $stmt = $conn->prepare("UPDATE candidates SET ScorePoints = ? WHERE id = ?");
    $stmt->bind_param("ii", $score, $candidateId);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();
        return true;
    } else {
        debug("Error: " . $stmt->error);
        $stmt->close();
        $conn->close();
        return false;
    }
}