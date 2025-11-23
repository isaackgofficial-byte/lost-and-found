// post.js - Camera, Upload, and Dark Mode for Post Page

document.addEventListener('DOMContentLoaded', () => {
    const imageInput = document.getElementById('image');
    const imagePreview = document.getElementById('imagePreview');
    const uploadIcons = document.querySelectorAll('.icon-btn');
    const themeToggle = document.querySelector('.theme-toggle');

    // ===== IMAGE UPLOAD HANDLING =====
    // Clicking camera or file icon triggers file input or camera
    uploadIcons.forEach(icon => {
        icon.addEventListener('click', () => {
            if (icon.textContent === '📁') {
                imageInput.click(); // select from device
            } else if (icon.textContent === '📷') {
                openCameraModal();
            }
        });
    });

    // Preview selected file
    imageInput.addEventListener('change', function () {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = (e) => {
                if (!imagePreview) return;
                imagePreview.src = e.target.result;
                imagePreview.style.display = 'block';
            };
            reader.readAsDataURL(this.files[0]);
        }
    });

    // ===== DARK MODE =====
    themeToggle.addEventListener('click', () => {
        document.body.classList.toggle('dark-mode');
        themeToggle.textContent = document.body.classList.contains('dark-mode') ? '☀️' : '🌙';
        localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
    });

    // Load saved theme
    if (localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark-mode');
        themeToggle.textContent = '☀️';
    }
});

// ===== CAMERA MODAL =====
let cameraStream = null;

function openCameraModal() {
    const modal = document.getElementById('cameraModal');
    const preview = document.getElementById('cameraPreview');
    const captureBtn = document.getElementById('capturePhoto');
    const photoCanvas = document.getElementById('photoCanvas');

    if (!modal || !preview || !captureBtn || !photoCanvas) return;

    modal.style.display = 'block';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
        .then(stream => {
            cameraStream = stream;
            preview.srcObject = stream;
        })
        .catch(err => alert('Camera access denied or unavailable'));

    captureBtn.onclick = () => {
        const ctx = photoCanvas.getContext('2d');
        photoCanvas.width = preview.videoWidth;
        photoCanvas.height = preview.videoHeight;
        ctx.drawImage(preview, 0, 0, photoCanvas.width, photoCanvas.height);

        photoCanvas.toBlob(blob => {
            const file = new File([blob], 'camera-photo.jpg', { type: 'image/jpeg' });
            const dt = new DataTransfer();
            dt.items.add(file);
            document.getElementById('image').files = dt.files;

            // Preview
            document.getElementById('imagePreview').src = URL.createObjectURL(blob);
            document.getElementById('imagePreview').style.display = 'block';

            closeCameraModal();
        }, 'image/jpeg', 0.9);
    };
}

function closeCameraModal() {
    const modal = document.getElementById('cameraModal');
    modal.style.display = 'none';
    if (cameraStream) {
        cameraStream.getTracks().forEach(track => track.stop());
        cameraStream = null;
    }
}
