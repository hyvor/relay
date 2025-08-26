
/**
 * https://stackoverflow.com/a/18650828
 */
export default function byteFormatter(bytes: number) {
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    if (bytes == 0) return '0 Bytes';
    const i = parseInt(String(Math.floor(Math.log(bytes) / Math.log(1000))));
    return Math.round(bytes / Math.pow(1000, i)) + ' ' + sizes[i];
}