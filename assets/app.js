// Import required CSS
import 'bootstrap/dist/css/bootstrap.min.css';
import '@fortawesome/fontawesome-free/css/all.min.css';
import 'simple-datatables/dist/style.css';

// Import required JS
import 'bootstrap';
import { DataTable } from 'simple-datatables';

// Initialize DataTables when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new DataTable('#datatablesSimple');
});