document.addEventListener('DOMContentLoaded', () => {

    // ======= Tab Switching =======
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');

    function switchTab(tabId) {
        tabs.forEach(t => t.classList.remove('active'));
        tabContents.forEach(c => c.classList.remove('active'));

        const activeTab = Array.from(tabs).find(t => t.dataset.tab === tabId);
        const activeContent = document.getElementById(tabId);

        if (activeTab) activeTab.classList.add('active');
        if (activeContent) activeContent.classList.add('active');
    }

    tabs.forEach(tab => tab.addEventListener('click', () => switchTab(tab.dataset.tab)));
    window.switchTab = switchTab;

    // ======= Avatar Utilities =======
    const avatarBox = document.getElementById('profileAvatar');
    const initialsElem = document.getElementById('avatarInitials');
    const avatarInput = document.getElementById('avatarUpload');

    function clearAvatarImage() {
        const img = avatarBox.querySelector('img');
        if (img) img.remove();
    }

    async function uploadAvatar(file) {
        const formData = new FormData();
        formData.append('avatar', file);

        try {
            const res = await fetch('profile.php?action=upload_avatar', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                clearAvatarImage();
                const img = document.createElement('img');
                img.src = data.path;
                img.alt = 'avatar';
                avatarBox.prepend(img);
                initialsElem.style.display = 'none';
                closeAvatarModal();
            } else {
                alert(data.error || 'Upload failed');
            }
        } catch (err) {
            console.error(err);
            alert('Upload failed');
        }
    }

    avatarInput.addEventListener('change', () => {
        if (avatarInput.files[0]) uploadAvatar(avatarInput.files[0]);
    });

    window.uploadAvatarFromDevice = () => avatarInput.click();

    // ======= Camera Handling =======
    let cameraStream = null;

    window.openCameraModal = () => {
        document.getElementById('cameraModal').style.display = 'flex';
        navigator.mediaDevices.getUserMedia({ video: true })
            .then(stream => {
                cameraStream = stream;
                document.getElementById('cameraPreview').srcObject = stream;
            })
            .catch(() => alert('Unable to access camera'));
    };

    window.closeCameraModal = () => {
        document.getElementById('cameraModal').style.display = 'none';
        if (cameraStream) cameraStream.getTracks().forEach(t => t.stop());
        cameraStream = null;
    };

    window.capturePhoto = () => {
        const canvas = document.getElementById('cameraCanvas');
        const video = document.getElementById('cameraPreview');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        canvas.toBlob(blob => uploadAvatar(blob), 'image/png');
    };

    window.openAvatarModal = () => document.getElementById('avatarModal').style.display = 'flex';
    window.closeAvatarModal = () => document.getElementById('avatarModal').style.display = 'none';

    // ======= Load Profile & Activity =======
    async function loadProfile() {
        try {
            const res = await fetch('profile.php?action=get_profile');
            const data = await res.json();
            const u = data.user;

            document.getElementById('profileName').textContent = `${u.first_name || ''}${u.last_name ? ' ' + u.last_name : ''}`;
            document.getElementById('profileEmail').textContent = u.email || '';
            document.getElementById('itemsCount').textContent = data.items_count || 0;
            document.getElementById('foundCount').textContent = data.found_count || 0;
            document.getElementById('memberSince').textContent = u.date_created ? new Date(u.date_created).getFullYear() : '';

            // Populate form
            document.getElementById('fullName').value = `${u.first_name || ''}${u.last_name ? ' ' + u.last_name : ''}`;
            document.getElementById('email').value = u.email || '';
            document.getElementById('phone').value = u.phone || '';
            document.getElementById('location').value = u.location || '';
            document.getElementById('bio').value = u.bio || '';
            document.getElementById('profileVisibility').checked = +u.profile_public === 1;
            document.getElementById('emailVisibility').checked = +u.email_public === 1;
            document.getElementById('phoneVisibility').checked = +u.phone_public === 1;
            document.getElementById('locationVisibility').checked = +u.location_public === 1;
            document.getElementById('activityVisibility').checked = +u.activity_public === 1;

            // Avatar
            clearAvatarImage();
            if (u.profile_image) {
                const img = document.createElement('img');
                img.src = u.profile_image;
                img.alt = 'avatar';
                avatarBox.prepend(img);
                initialsElem.style.display = 'none';
            } else {
                const initials = `${u.first_name?.[0] || ''}${u.last_name?.[0] || ''}`.toUpperCase() || (u.email ? u.email[0].toUpperCase() : '');
                initialsElem.textContent = initials;
                initialsElem.style.display = 'flex';
            }

            loadActivity();
        } catch (e) {
            console.error('Failed to load profile', e);
        }
    }

    async function loadActivity() {
        try {
            const res = await fetch('profile.php?action=get_activity');
            const data = await res.json();
            const container = document.getElementById('activityList');
            container.innerHTML = '';
            if (data.activity?.length) {
                data.activity.forEach(a => {
                    const div = document.createElement('div');
                    div.className = 'activity-item';
                    div.textContent = `${a.title} [${a.type}-${a.status}]`;
                    container.appendChild(div);
                });
            } else container.textContent = 'No recent activity';
        } catch (e) {
            console.error('Failed to load activity', e);
        }
    }

    // ======= Forms =======
    document.getElementById('profileForm').addEventListener('submit', async e => {
        e.preventDefault();
        const payload = {
            fullName: document.getElementById('fullName').value,
            email: document.getElementById('email').value,
            phone: document.getElementById('phone').value,
            location: document.getElementById('location').value,
            bio: document.getElementById('bio').value
        };
        try {
            const res = await fetch('profile.php?action=update_profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            alert(data.message || data.error);
            if (data.success) loadProfile();
        } catch { alert('Update failed'); }
    });

    document.getElementById('passwordForm').addEventListener('submit', async e => {
        e.preventDefault();
        if (document.getElementById('newPassword').value !== document.getElementById('confirmPassword').value) return alert('Passwords do not match');

        const payload = {
            currentPassword: document.getElementById('currentPassword').value,
            newPassword: document.getElementById('newPassword').value
        };

        try {
            const res = await fetch('profile.php?action=change_password', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            alert(data.message || data.error);
            if (data.success) e.target.reset();
        } catch { alert('Password change failed'); }
    });

    document.getElementById('privacyForm').addEventListener('submit', async e => {
        e.preventDefault();
        const payload = {
            profile: +document.getElementById('profileVisibility').checked,
            email: +document.getElementById('emailVisibility').checked,
            phone: +document.getElementById('phoneVisibility').checked,
            location: +document.getElementById('locationVisibility').checked,
            activity: +document.getElementById('activityVisibility').checked
        };

        try {
            const res = await fetch('profile.php?action=update_privacy', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            alert(data.message || data.error);
            if (data.success) loadProfile();
        } catch { alert('Saving privacy failed'); }
    });

    // ======= Initial Load =======
    loadProfile();
});
