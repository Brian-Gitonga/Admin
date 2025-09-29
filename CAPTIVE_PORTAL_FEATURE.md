# Downloadable Captive Portal Feature Documentation

## Overview
The downloadable captive portal feature allows ISP owners to generate and download a complete HTML captive portal page that can be uploaded to any router system. This feature integrates seamlessly with our SaaS hotspot payment gateway.

## How It Works

### 1. **Access the Feature**
- Navigate to the portal page with a specific router selected: `portal.php?router_id=X&business=YourBusiness`
- The "Download Captive Portal" button appears in the footer section when a router is selected
- Only shows for authenticated users with valid router configurations

### 2. **Generate Captive Portal**
- Click the "Download Captive Portal" button
- The system generates a customized HTML file with:
  - Business branding and router information
  - Inline CSS (no external dependencies)
  - MikroTik-compatible form structure
  - Mobile-responsive design
  - Integration with your payment gateway

### 3. **Upload to Router**
- Upload the downloaded HTML file to your router's hotspot login page
- Compatible with MikroTik and other router systems that support custom login pages
- The file is completely self-contained with inline CSS and JavaScript

## Features Included

### **User Interface**
- ✅ Clean, professional design matching portal branding
- ✅ Mobile-responsive layout
- ✅ Tab-based interface (Voucher Code / Username-Password)
- ✅ Loading animations and user feedback
- ✅ Error handling and validation

### **Authentication Methods**
- ✅ Voucher code login (primary method)
- ✅ Username/password login (secondary method)
- ✅ CHAP authentication support for MikroTik
- ✅ Form validation and error messages

### **Payment Integration**
- ✅ "Buy WiFi Package" button linking to your portal
- ✅ Package preview showing available plans
- ✅ "I have paid - Refresh Page" functionality
- ✅ Auto-detection of payment completion
- ✅ Support contact information

### **Customization**
- ✅ Business name and branding
- ✅ Router-specific parameters
- ✅ Custom support phone numbers
- ✅ Branded color scheme (yellow theme)
- ✅ Dynamic package information

## Technical Details

### **Generated File Structure**
```
captive_portal_[RouterName].html
├── Inline CSS (complete styling)
├── HTML Structure
│   ├── Header (business branding)
│   ├── Login Forms (voucher & username)
│   ├── Package Preview
│   ├── Buy Package Button
│   └── Footer (support info)
└── JavaScript (form handling, validation, MD5)
```

### **Router Compatibility**
- **MikroTik RouterOS**: Full compatibility with hotspot login page
- **Other Routers**: Compatible with standard captive portal systems
- **Form Variables**: Uses standard `$(link-login-only)`, `$(link-orig)`, etc.
- **CHAP Support**: Includes MD5 hashing for secure authentication

### **Mobile Optimization**
- Responsive design for all screen sizes
- Touch-friendly interface elements
- Optimized form inputs for mobile keyboards
- Fast loading with inline resources

## Payment Flow

### **Step 1: User Connects to WiFi**
1. User connects to hotspot WiFi
2. Router redirects to captive portal (your uploaded HTML file)
3. User sees login page with voucher input and "Buy Package" button

### **Step 2: Purchase Process**
1. User clicks "Buy WiFi Package"
2. Opens your portal.php in new tab/window
3. User completes payment (M-Pesa/Paystack)
4. User receives voucher code via SMS/email

### **Step 3: WiFi Access**
1. User returns to captive portal
2. Enters voucher code in the form
3. Router authenticates and grants internet access
4. User is connected to WiFi

## Setup Instructions

### **For ISP Owners**
1. **Generate Portal**: Visit your portal page and click "Download Captive Portal"
2. **Upload to Router**: Upload the downloaded HTML file to your router's hotspot login page
3. **Configure Router**: Set up hotspot to redirect users to the login page
4. **Test**: Connect a device and test the complete flow

### **Router Configuration Examples**

#### **MikroTik RouterOS**
```bash
# Upload the HTML file to router
/file upload captive_portal_YourRouter.html

# Set as hotspot login page
/ip hotspot walled-garden ip
add action=accept dst-address=your-portal-domain.com

/ip hotspot profile
set hsprof1 login-by=http-chap,cookie html-directory=hotspot
```

#### **Other Routers**
- Upload HTML file to captive portal directory
- Configure redirect to the uploaded file
- Ensure form submission points to router's authentication endpoint

## Troubleshooting

### **Common Issues**
1. **Download Not Working**: Ensure router is selected in portal URL
2. **Form Not Submitting**: Check router's authentication endpoint configuration
3. **Styling Issues**: File includes all CSS inline - no external dependencies needed
4. **Mobile Issues**: File is fully responsive - test on actual devices

### **Testing Checklist**
- [ ] Download generates correct filename
- [ ] HTML file opens properly in browser
- [ ] Forms validate input correctly
- [ ] "Buy Package" button opens portal in new tab
- [ ] Mobile layout displays correctly
- [ ] Router authentication works with uploaded file

## Security Features

### **Authentication Security**
- CHAP authentication support for secure password transmission
- MD5 hashing implementation included
- Form validation prevents empty submissions
- CSRF protection through router's built-in mechanisms

### **Data Protection**
- No sensitive data stored in HTML file
- All authentication handled by router
- Payment processing through secure gateway
- Support contact information only

## Customization Options

### **Available Customizations**
- Business name and display name
- Router-specific branding
- Support contact information
- Package information display
- Color scheme and styling
- Custom portal URL parameters

### **Future Enhancements**
- Multiple language support
- Custom logo upload
- Advanced styling options
- Analytics integration
- Social media login options

## Support

For technical support or feature requests:
- Contact: Support phone number from reseller profile
- Documentation: This file and system documentation
- Testing: Use the portal page to test functionality

---

**Note**: This feature is part of the SaaS hotspot payment gateway system and requires active reseller account with configured routers and packages.
