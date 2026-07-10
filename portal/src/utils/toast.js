import { toast } from 'react-toastify';

export function getToastMessage(error, fallback = 'Something went wrong.') {
	if (typeof error === 'string' && error.trim()) {
		return error;
	}

	if (error?.message) {
		return error.message;
	}

	return fallback;
}

export function toastPromise(action, messages = {}, options = {}) {
	const promise = typeof action === 'function' ? action() : action;

	return toast.promise(
		promise,
		{
			pending: messages.loading || 'Working…',
			success: {
				render({ data }) {
					if (typeof messages.success === 'function') {
						return messages.success(data);
					}

					return messages.success || 'Done.';
				},
			},
			error: {
				render({ data }) {
					if (typeof messages.error === 'function') {
						return messages.error(data);
					}

					return getToastMessage(data, messages.error || 'Something went wrong.');
				},
			},
		},
		options
	);
}

export function toastSuccess(message, options = {}) {
	return toast.success(message, options);
}

export function toastError(error, fallback = 'Something went wrong.', options = {}) {
	return toast.error(getToastMessage(error, fallback), options);
}
