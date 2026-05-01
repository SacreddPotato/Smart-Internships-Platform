import api from "./axios";

export function fetchProfile() {
    return api.get('/student/profile');
}

export function updateProfile(payload) {
    return api.put('/student/profile', payload);
}

export function uploadCv(file, onUploadProgress) {
    const formData = new FormData();
    formData.append('cv', file);

    return api.post('/student/profile/cv', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
        onUploadProgress
    });
}

export function syncSkills(skillIds) {
    return api.put('/student/skills', { skills: skillIds });
}
