export default function ErrorAler({ message}) {
    if (!message) return null;

    return (
        <div className='alert alert-error' role='alert'>
            {message}
        </div>
    );
}
