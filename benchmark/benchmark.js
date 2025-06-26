import http from 'k6/http';
import { sleep } from 'k6';

export const options = {
    iterations: 1000,
    vus: 50,
};

export default function () {
    
    const res = http.post(
        'http://hyvor-relay-backend/api/console/email',
        {
            from: 'supun@hyvor.local.testing',
            // hyvor.local.testing is resolved to hyvor-service-mailpit for testing purposes
            to: 'ishini@hyvor.local.testing',
            subject: 'My subject',
            body_html: '<p>This is the HTML</p>',
            body_text: 'This is the text',
        },
        {
            headers: {
                'X-Project-Id': '1',
            }
        }
    );

    //console.log(res.body);

    sleep(1);
}
