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





$(function () {
    $("#wizard").steps({
        headerTag: "h4",
        bodyTag: "section",
        transitionEffect: "fade",
        enableAllSteps: true,
        transitionEffectSpeed: 500,
        onStepChanging: function (event, currentIndex, newIndex) {
            // Validate first screen (Step 0) required fields
            if (currentIndex === 0) {
                if (!validateStep1()) {
                    // Load SweetAlert2
                
                    showAlert('Please fill all required fields in Step 1', 'error');

                    return false;
                }
                else {
                    showAlert('We sent you a code via email, enter the OTP to proceed', 'success');

                }
            }

                // Validate OTP on moving to Step 2
                if (currentIndex === 1 && newIndex === 2) {
                    if (!validateStep2()) {
                        showAlert('Invalid OTP. Try again or go back and change email', 'error');
                        return false;
                    }
                    else {
                        $('.steps ul').removeClass('step-2');
                        $('.steps ul').addClass('step-3');

                        showAlert('Your email account was verified. Proceed', 'success');

                    }
                }

            // Validate third screen (Step 2) required fields
            if (currentIndex === 2) {
                if (!validateStep3()) {
                    showAlert('Please fill all required fields in Step 3.', 'error');

                    return false;
                }
            }

            if (newIndex === 1) {
                $('.steps ul').addClass('step-2');


                saveFormData(currentIndex);
            } else {
                $('.steps ul').removeClass('step-2');
            }
            if (newIndex === 2) {
                $('.steps ul').addClass('step-3');
                saveFormData(currentIndex);
            } else {
                $('.steps ul').removeClass('step-3');
            }

            if (newIndex === 3) {
                $('.steps ul').addClass('step-4');
                $('.actions ul').addClass('step-last');
                saveFormData(currentIndex);
            } else {
                $('.steps ul').removeClass('step-4');
                $('.actions ul').removeClass('step-last');
            }
            return true;
        },
        labels: {
            finish: "Place Holder",
            next: "Next",
            previous: "Previous"
        }
    });

    // Step 1: User Registration
    $('#nextStep1').on('click', function () {

        // Generate OTP
        let otp = Math.floor(100000 + Math.random() * 900000).toString();
        console.log(otp);
        localStorage.setItem('otp', otp);

        // Save form data
        saveFormData(0);

        // Send OTP
        $.ajax({
            url: 'sendOtp.php',
            method: 'POST',
            data: { email: $('#email').val(), otp: otp },
            success: function (response) {
                if (response.success) {
                    alert('OTP sent to your email');
                    $("#wizard").steps('next');
                } else {
                    alert('Error sending OTP');
                }
            }
        });
    });

    // Step 2: OTP Verification
    $('#nextStep2').on('click', function () {
        let enteredOtp = $('#otp').val();
        let storedOtp = localStorage.getItem('otp');
        if (enteredOtp === storedOtp) {
            $("#wizard").steps('next');
        } else {
            alert('Invalid OTP');
        }
    });

    // Step 3: Additional Information
    $('#nextStep3').on('click', function () {
        saveFormData(2);
        submitForm();
    });

    // Fetch GPS Location
    $('#fetchLocation').on('click', function () {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function (position) {
                var lat = position.coords.latitude;
                var lng = position.coords.longitude;
                $('#gpsLocation').val(lat + ',' + lng);
                alert('GPS Location Fetched: ' + lat + ', ' + lng);
                console.log('GPS Location Fetched:', { latitude: lat, longitude: lng });
            }, function (error) {
                alert('Error fetching GPS location: ' + error.message);
            });
        } else {
            alert('Geolocation is not supported by this browser.');
        }
    });

    function saveFormData(stepIndex) {
        let formData = {};
        if (stepIndex === 0) {
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
            console.log('Step 1 Data Saved:', formData);
            // alert('Step 1 Data Saved. Check console for details.');
        } else if (stepIndex === 1) {
            formData = {
                otp: $('#otp').val()
            };
            console.log('Step 2 Data Saved:', formData);
            alert('Step 2 Data Saved. Check console for details.');
        } else if (stepIndex === 2) {
            formData = {
                maritalStatus: $('#maritalStatus').val(),
                height: $('#height').val(),
                country: $('#country').val(),
                iq: $('#iq').val(),
                gpsLocation: $('#gpsLocation').val(),
                photo: $('#photo')[0].files[0]
            };
            console.log('Step 3 Data Saved:', formData);
            alert('Step 3 Data Saved. Check console for details.');
        }
        localStorage.setItem('formDataStep' + stepIndex, JSON.stringify(formData));
    }

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
            const enteredOtp = $('#otp').val();
            const storedOtp = localStorage.getItem('otp');
    
            if (enteredOtp === storedOtp) {
                showAlert('OTP verified successfully!', 'success');
                // Proceed to the next step
                $("#wizard").steps('next');
            } else {
                showAlert('Invalid OTP. Please try again.', 'error');
            }
        }
    function validateStep3() {
        let valid = true;
        $('#maritalStatus, #height, #country, #iq, #gpsLocation, #photo').each(function () {
            if ($(this).val() === '') {
                valid = false;
            }
        });
        return valid;
    }



    function getFormData() {
        let formData = {};
        for (let i = 0; i <= 2; i++) {
            let stepData = JSON.parse(localStorage.getItem('formDataStep' + i));
            if (stepData) {
                formData = { ...formData, ...stepData };
            }
        }
        return formData;
    }

    function submitForm() {
        let formData = getFormData();
        let formDataObj = new FormData();
        for (let key in formData) {
            formDataObj.append(key, formData[key]);
        }

        $.ajax({
            url: 'submitCandidate.php',
            method: 'POST',
            data: formDataObj,
            processData: false,
            contentType: false,
            success: function (response) {
                if (response.success) {
                    $('#scoreCard').html(response.scoreCardHtml);
                    $('#downloadPdf').attr('href', response.pdfUrl);
                    alert('Form submitted successfully. Check console for details.');
                    console.log('Form submitted successfully:', response);
                    $("#wizard").steps('next');
                } else {
                    alert('Error submitting data');
                    console.log('Error submitting data:', response);
                }
            }
        });
    }
});
