# Ticket System

A minimalist ticketing system built with PHP, MySQL. Designed for efficient ticket management with role-based access control.

## Features

### User Management
- Multi-role system (Admin, Technician, User)
- User creation and management
- Activity status tracking

### Ticket Management
- Create, update, and track tickets
- Priority levels (Low, Medium, High, Critical)
- Status tracking (Open, In Progress, Pending, Resolved, Closed)
- Category organization
- Assignment system
- Comment threads
- Complete audit history

### Administration
- Dashboard with statistics
- User management interface
- Technician management
- Ticket oversight
- Category and priority configuration

## Database Schema

### Core Tables
- `users` - User accounts and authentication
- `tickets` - Ticket records
- `priorities` - Priority levels
- `statuses` - Ticket statuses
- `categories` - Ticket categories
- `comments` - Ticket discussions
- `ticket_history` - Audit trail

## Security Features

- Password hashing (bcrypt)
- SQL injection prevention (prepared statements)
- XSS protection (input sanitization)
- CSRF token implementation
- Session management
- Role-based access control

## Project Structure

```
ticketing-system/
├── admin/              # Admin panel
├── technician/         # Technician panel
├── user/               # User panel
├── assets/
│   └── css/           # Stylesheets
├── config/            # Configuration files
├── database/          # SQL schemas
└── includes/          # Shared components
```

## License

MIT License - See LICENSE file for details

## Author

David Sangouard  
[github.com/davidsangouard](https://github.com/davidsangouard)

---

*Built with attention to detail and minimal design principles*

