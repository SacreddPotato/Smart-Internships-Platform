import { useState } from "react";
import * as applicationApi from "../../../api/applicationApi";
import ErrorAlert from "../../common/ErrorAlert";

export default function ApplyModal({ internship, open, onClose, onApplied }) {
    const [message, setMessage] = useState("");
    const [submitting, setSubmitting] = useState(false);
    const [error, setError] = useState(null);

    if (!open) return null;

    async function handleSubmit(event) {
        event.preventDefault();
        setSubmitting(true);
        setError(null);

        try {
            await applicationApi.apply(internship.id, { message });
            setMessage("");
            onApplied();
            onClose();
        } catch (err) {
            setError(
                err.response?.data?.message ||
                    "An error occurred while applying.",
            );
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="modal-backdrop">
            <div className="modal-panel">
                <div className="modal-header flex flex-row justify-between align-items-center mb-2">
                    <h2 className="section-title">
                        Apply to {internship.title}
                    </h2>
                    <button
                        className="btn btn-ghost"
                        type="button"
                        onClick={onClose}
                    >
                        Close
                    </button>
                </div>
                <ErrorAlert message={error} />

                <form className="form-stack mt-2">
                    <label className="form-group">
                        <span>Message</span>
                        <textarea
                            className="form-textarea"
                            value={message}
                            onChange={(event) => setMessage(event.target.value)}
                            placeholder="Briefly explain why you are interested in this internship..."
                        />
                    </label>

                    <button
                        className="btn btn-primary"
                        type="submit"
                        onClick={handleSubmit}
                        disabled={submitting}
                    >
                        {submitting ? "Applying..." : "Apply"}
                    </button>
                </form>
            </div>
        </div>
    );
}
