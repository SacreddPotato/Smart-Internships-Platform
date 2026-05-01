import { useState } from 'react';

export default function FIleUpload({ accept, onUpload }) {
    const [file, setFile] = useState(null);

    function handleSubmit(event) {
        event.preventDefault();

        if (file) {
            onUpload(file);
        }
    }

    return (
        <form className='form-stack' onSubmit={handleSubmit}>
            <input
                className='form-input'
                type='file'
                accept={accept}
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />

            <button className='btn btn-seconday' type='submit' disabled={!file}>
                Upload CV
            </button>
        </form>
    );
}
