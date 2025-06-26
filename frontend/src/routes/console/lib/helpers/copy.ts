import { toast } from '@hyvor/design/components';

export default function copy(str: string) {
	navigator.clipboard.writeText(str);
}
export function copyAndToast(str: string, message: string | null = null) {
	copy(str);
	toast.success(message || 'Copied to clipboard');
}
