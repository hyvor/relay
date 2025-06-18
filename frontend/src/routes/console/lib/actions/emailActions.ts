import type { Email, EmailStatus } from "../../types";
import consoleApi from "../consoleApi";

export function getEmails(
	status: EmailStatus | null,
	from_search : string | null,
    to_search : string | null,
	limit: number,
	offset: number
) {
	return consoleApi.get<Email[]>({
		endpoint: 'emails',
		data: {
			status,
			from_search,
            to_search,
			limit,
			offset
		}
	});
}