// Load Javascript Maps function
function initMap() {
    console.log('Google Maps API loaded successfully');
    // Your initialization code for Google Maps
}



$(function () {
    $("#wizard").steps({
        headerTag: "h4",
        bodyTag: "section",
        transitionEffect: "fade",
        enableAllSteps: true,
        transitionEffectSpeed: 500,
        onStepChanging: function (event, currentIndex, newIndex) {
            console.log("Step changing from " + currentIndex + " to " + newIndex);

            // Validate first screen (Step 0) required fields
            if (currentIndex === 0 && newIndex === 1) {
                if (!validateStep1()) {
                    showAlert('Please fill all required fields in Step 1', 'error');
                    return false;
                } else {
                    showAlert('We sent you a code via email, enter the OTP to proceed', 'success');
                    saveFormData(currentIndex);
                }
            }

            // Validate OTP on moving to Step 2
            if (currentIndex === 1 && newIndex === 2) {
                if (!validateStep2()) {
                    showAlert('Invalid OTP. Try again or go back and change email', 'error');
                    return false;
                } else {
                    saveFormData(currentIndex);
                }
            }

            // Validate third screen (Step 2) required fields and submit form
            if (currentIndex === 2 && newIndex === 3) {
                if (!validateStep3()) {
                    showAlert('Please fill all required fields in Step 3.', 'error');
                    return false;
                } else {
                    saveFormData(currentIndex);
                    submitForm();
                    return false; // Prevent moving to the next step until the form is submitted
                }
            }

            return true;
        },
        labels: {
            finish: "Submit",
            next: "Next",
            previous: "Previous"
        },
        onFinishing: function (event, currentIndex) {
            // Ensure form submission on finishing
            if (currentIndex === 2) {
                submitForm();
                return false; // Prevent finishing the wizard until the form is submitted
            }
            return true;
        }
    });

});
// Fetch GPS Location
if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function (position) {
        var lat = position.coords.latitude;
        var lng = position.coords.longitude;
        var gpsLocation = lat + ',' + lng;
        $('#gpsLocation').val(gpsLocation);
        localStorage.setItem('gpsLocation', gpsLocation);
        console.log('GPS Location Fetched:', gpsLocation);
    }, function (error) {
        console.error('Error fetching GPS location: ' + error.message);
    });
} else {
    console.error('Geolocation is not supported by this browser.');
}




// Function to load SweetAlert2 script
function loadSweetAlert(callback) {
    let script = document.createElement('script');
    script.type = 'text/javascript';
    script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    script.onload = callback;
    document.head.appendChild(script);
}

// Function to show SweetAlert2 toast
function showAlert(message, type) {
    Swal.fire({
        icon: type,
        title: message,
        toast: true,
        position: 'center',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });
}


// output results
function renderScoreCard(data) {
    const scoreCardHtml = `
        <div class='container'>
            <div class='header'>
                <img src='Resource/${data.photoUrl}' alt='Photo' style='width: 100px;'>
                <h1>Tenakata University Admission Scorecard</h1>
            </div>
            <div class='details'>
                <h3>Candidate Details</h3>
                <p><strong>First Name:</strong> ${data.firstName}</p>
                <p><strong>Last Name:</strong> ${data.lastName}</p>
                <p><strong>Email:</strong> ${data.email}</p>
                <p><strong>Gender:</strong> ${data.gender}</p>
                <p><strong>Age:</strong> ${data.age}</p>
                <p><strong>Marital Status:</strong> ${data.maritalStatus}</p>
                <p><strong>GPS Location:</strong> ${data.gpsLocation}</p>
                <p><strong>Country:</strong> ${data.country}</p>
                <p><strong>IQ Score:</strong> ${data.iqScore}</p>
                <p><strong>Height:</strong> ${data.height}</p>
            </div>
            <div class='score'>
                <h3>Ranking: ${data.ranking}</h3>
                <h3>Score Points: ${data.scorePoints}</h3>
            </div>
        </div>
    `;
    $('#scoreCard').html(scoreCardHtml);
}



// preprocess form data for submission
function getFormData() {
    let formData = {};
    for (let i = 0; i <= 2; i++) {
        const stepData = JSON.parse(localStorage.getItem('formDataStep' + i));
        if (stepData) {
            formData = { ...formData, ...stepData };
        }
    }

        // Ensure gpsLocation is added to the form data
        if (localStorage.getItem('gpsLocation')) {
            formData.gpsLocation = localStorage.getItem('gpsLocation');
        }

        
    return formData;
}

function submitForm() {
    const formData = getFormData();
    const formDataObj = new FormData();
    for (const key in formData) {
        if (key === 'photo') {
            const fileInput = document.getElementById('photo');
            if (fileInput.files.length > 0) {
                formDataObj.append(key, fileInput.files[0], fileInput.files[0].name);
            }
        } else {
            formDataObj.append(key, formData[key]);
        }
    }

    $.ajax({
        url: 'http://localhost/TenakataUni/db.php',
        method: 'POST',
        data: formDataObj,
        processData: false,
        contentType: false,
        success: function (response) {
            try {
                response = JSON.parse(response);
                if (response.success) {
                                        // Navigate to the last step


                    renderScoreCard(response.scoreCardHtml);
                    $('#downloadPdf').attr('href', response.pdfUrl);

                    showAlert('Form submitted successfully.', 'success');
                } else {
                    showAlert('Error submitting data.', 'error');
                }
            } catch (e) {
                showAlert('Unexpected server response. Please try again later.', 'error');
                console.error('Error parsing JSON response: ', e);
            }
        },
        error: function (jqXHR, textStatus, errorThrown) {
            showAlert('Error submitting data', 'error');
            console.error('AJAX error: ', textStatus, errorThrown);
        }
    });
}

// 

// Field Validation to ensure all fields are filled out. 

function validateStep1() {
    let valid = true;
    $('#firstName, #lastName, #email, #gender, #age, #password, #confirmPassword, #agree').each(function () {
        if ($(this).val() === '' || !$(this).prop('checked') && this.id === 'agree') {
            valid = false;
        }

        // Generate OTP
        let otp = Math.floor(100000 + Math.random() * 900000).toString();
        console.log(otp);
        localStorage.setItem('otp', otp);

    });
    return valid;
}


// Validate OTP
function validateStep2() {

    let valid = true;

    const enteredOtp = $('#otp').val();
    const storedOtp = localStorage.getItem('otp');

    if (enteredOtp === storedOtp) {
        showAlert('OTP verified successfully!', 'success');
        // Proceed to the next step
        return valid;
    } else {
        showAlert('Invalid OTP. Please try again.', 'error');
    }
}
function validateStep3() {
    let valid = true;
    $('#maritalStatus, #height, #country, #iq, #photo').each(function () {
        if ($(this).val() === '') {
            valid = false;
        }
    });
    return valid;
}



function saveFormData(stepIndex) {
    let formData = {};
    switch (stepIndex) {
        case 0:
            formData = {
                firstName: $('#firstName').val(),
                lastName: $('#lastName').val(),
                email: $('#email').val(),
                gender: $('#gender').val(),
                age: $('#age').val(),
                password: $('#password').val(),
                confirmPassword: $('#confirmPassword').val(),
                agree: $('#agree').prop('checked')
            };
            break;
        case 1:
            formData = { otp: $('#otp').val() };
            break;
        case 2:
            formData = {
                maritalStatus: $('#maritalStatus').val(),
                height: $('#height').val(),
                country: $('#country').val(),
                iq: $('#iq').val(),
                gpsLocation: $('#gpsLocation').val(),
                photo: $('#photo')[0].files[0]
            };
            break;
    }
    localStorage.setItem('formDataStep' + stepIndex, JSON.stringify(formData));
}


