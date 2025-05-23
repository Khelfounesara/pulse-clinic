// Doctor Management
document.addEventListener('DOMContentLoaded', function() {
    // Load doctors list
    loadDoctorsList();
    
    // Handle add doctor form submission
    document.getElementById('addDoctorForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });
        
        // Add action to data
        data.action = 'add_doctor';
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="fas fa-spinner spinner"></i> Adding...';
        submitBtn.disabled = true;
        
        // Send AJAX request
        fetch('doctor_management.php', {
            method: 'POST',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json'
            }
        })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                alert(result.message);
                this.reset();
                // Refresh doctors list
                loadDoctorsList();
                // Switch to doctors list view
                document.querySelector('.menu-item[data-section="doctors"]').click();
            } else {
                alert(result.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        })
        .finally(() => {
            submitBtn.innerHTML = '<i class="fas fa-save"></i><span>Add Doctor</span>';
            submitBtn.disabled = false;
        });
    });
    
    // Search and filter doctors
    document.querySelector('#doctors-section .search-input').addEventListener('input', function() {
        loadDoctorsList();
    });
    
    document.querySelector('#doctors-section .filter-select').addEventListener('change', function() {
        loadDoctorsList();
    });
});

function loadDoctorsList() {
    const search = document.querySelector('#doctors-section .search-input').value;
    const specialty = document.querySelector('#doctors-section .filter-select').value;
    
    fetch('doctor_management.php', {
        method: 'POST',
        body: JSON.stringify({
            action: 'get_doctors',
            search: search,
            specialty: specialty
        }),
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(response => response.json())
    .then(doctors => {
        if (Array.isArray(doctors)) {
            renderDoctorsList(doctors);
        } else if (doctors.error) {
            console.error('Error:', doctors.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

function renderDoctorsList(doctors) {
    const tbody = document.querySelector('#doctors-section table tbody');
    tbody.innerHTML = '';
    
    if (doctors.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No doctors found</td></tr>';
        return;
    }
    
    doctors.forEach(doctor => {
        const initials = getInitials(doctor.full_name);
        const status = doctor.availability_status ? 'Active' : 'Inactive';
        const statusClass = doctor.availability_status ? 'badge-success' : 'badge-warning';
        
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>#DR${doctor.user_id.toString().padStart(4, '0')}</td>
            <td>
                <div class="d-flex align-center gap-2">
                    <div class="user-avatar sm">${initials}</div>
                    <div>${doctor.full_name}</div>
                </div>
            </td>
            <td>${doctor.specialty}</td>
            <td>${doctor.experience_years} years</td>
            <td>${doctor.qualification}</td>
            <td><span class="badge ${statusClass}">${status}</span></td>
            <td>
                <div class="d-flex gap-2">
                    <button class="btn btn-primary btn-sm view-doctor" data-id="${doctor.user_id}">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn btn-success btn-sm edit-doctor" data-id="${doctor.user_id}">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-danger btn-sm delete-doctor" data-id="${doctor.user_id}">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    // Add event listeners to the new buttons
    document.querySelectorAll('.view-doctor').forEach(btn => {
        btn.addEventListener('click', function() {
            const doctorId = this.getAttribute('data-id');
            viewDoctorDetails(doctorId);
        });
    });
    
    document.querySelectorAll('.edit-doctor').forEach(btn => {
        btn.addEventListener('click', function() {
            const doctorId = this.getAttribute('data-id');
            editDoctor(doctorId);
        });
    });
    
    document.querySelectorAll('.delete-doctor').forEach(btn => {
        btn.addEventListener('click', function() {
            const doctorId = this.getAttribute('data-id');
            deleteDoctor(doctorId);
        });
    });
}

function getInitials(name) {
    return name.split(' ').map(part => part[0]).join('').toUpperCase();
}

function viewDoctorDetails(doctorId) {
    // In a real application, you would fetch the doctor details from the server
    // For this example, we'll just show the modal with placeholder data
    const modal = document.getElementById('viewDoctorModal');
    modal.classList.add('active');
}

function editDoctor(doctorId) {
    // In a real application, you would fetch the doctor details and populate a form
    alert(`Edit doctor with ID: ${doctorId}`);
}

function deleteDoctor(doctorId) {
    if (confirm('Are you sure you want to delete this doctor?')) {
        // In a real application, you would send a request to delete the doctor
        alert(`Doctor with ID: ${doctorId} deleted successfully!`);
        loadDoctorsList();
    }
}