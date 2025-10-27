<?php
// Start session and include database connection
session_start();
require_once 'portal_connection.php';

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
$router_id = isset($input['router_id']) ? intval($input['router_id']) : 0;
$reseller_id = isset($input['reseller_id']) ? intval($input['reseller_id']) : 0;

// Get router information
$routerInfo = null;
if ($router_id > 0) {
    $routerQuery = "SELECT * FROM hotspots WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($routerQuery);
    $stmt->bind_param("i", $router_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $routerInfo = $result->fetch_assoc();
    }
}

// Get reseller information
$resellerInfo = null;
if ($reseller_id > 0) {
    $resellerQuery = "SELECT * FROM resellers WHERE id = ? LIMIT 1";
    $stmt = $conn->prepare($resellerQuery);
    $stmt->bind_param("i", $reseller_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $resellerInfo = $result->fetch_assoc();
    }
}

// Set default values
$businessName = $resellerInfo && isset($resellerInfo['business_name']) ? $resellerInfo['business_name'] : 'WiFi Service';
$routerName = $routerInfo ? $routerInfo['name'] : 'WiFi Router';
$supportPhone = $resellerInfo && isset($resellerInfo['phone']) ? $resellerInfo['phone'] : '+254750059353';

// Generate safe filename
$safeRouterName = $routerInfo ? preg_replace('/[^a-zA-Z0-9_-]/', '_', $routerInfo['name']) : 'router';
$filename = 'captive_portal_' . $safeRouterName . '.html';

// Generate the portal URL for buying vouchers - must include router_id and business parameters
$portalUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/portal.php";

// Build URL parameters
$urlParams = [];
if ($router_id > 0) {
    $urlParams[] = "router_id=" . $router_id;
} else {
    // Fallback - should not happen but ensures URL always has router_id
    $urlParams[] = "router_id=1";
}

// Add business parameter if available
if ($resellerInfo && isset($resellerInfo['business_name'])) {
    $urlParams[] = "business=" . urlencode($resellerInfo['business_name']);
} else {
    // Fallback business name
    $urlParams[] = "business=" . urlencode('Demo');
}

// Combine URL with parameters
$portalUrl .= "?" . implode("&", $urlParams);

// Set content type for HTML download
header('Content-Type: text/html');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Generate simple, lightweight captive portal HTML
echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WiFi Login - ' . htmlspecialchars($businessName) . '</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
        }

        .header {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .subtitle {
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 30px 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #333;
        }

        .form-input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
        }

        .form-input:focus {
            outline: none;
            border-color: #f59e0b;
        }

        .connect-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 15px;
        }

        .connect-btn:hover {
            background: linear-gradient(135deg, #d97706, #b45309);
        }

        .buy-voucher-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            color: white;
            text-decoration: none;
            text-align: center;
            border-radius: 8px;
            font-weight: bold;
            margin-bottom: 15px;
        }

        .buy-voucher-btn:hover {
            background: #2563eb;
        }

        .help-text {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-top: 15px;
        }

        .support-link {
            color: #f59e0b;
            text-decoration: none;
        }

        @media (max-width: 480px) {
            .container {
                margin: 10px;
            }
            
            .header {
                padding: 20px 15px;
            }
            
            .content {
                padding: 20px 15px;
            }
            
            .title {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="title">' . htmlspecialchars($businessName) . '</h1>
            <p class="subtitle">WiFi Login Portal</p>
        </div>
        
        <div class="content">
            <!-- CHAP Authentication Hidden Form (Required by MikroTik) -->
            $(if chap-id)
            <form name="sendin" action="$(link-login-only)" method="post" style="display:none">
                <input type="hidden" name="username" />
                <input type="hidden" name="password" />
                <input type="hidden" name="dst" value="$(link-orig)" />
                <input type="hidden" name="popup" value="true" />
            </form>
            $(endif)

            <!-- Voucher Login Form -->
            <form name="login" action="$(link-login-only)" method="post"$(if chap-id) onSubmit="return doLogin()"$(endif)>
                <input type="hidden" name="dst" value="$(link-orig)" />
                <input type="hidden" name="popup" value="true" />

                <div class="form-group">
                    <label class="form-label" for="username">Voucher Code</label>
                    <input name="username" id="username" type="text" class="form-input" placeholder="Enter your voucher code" required />
                </div>

                $(if chap-id)
                <div class="form-group" style="display:none;">
                    <input name="password" id="password" type="password" value="" />
                </div>
                $(endif)

                <button type="submit" class="connect-btn">Connect to WiFi</button>
            </form>
            
            <!-- Buy Voucher Button -->
            <a href="' . htmlspecialchars($portalUrl) . '" target="_blank" class="buy-voucher-btn">
                Buy WiFi Voucher
            </a>
            
            <div class="help-text">
                Need help? Contact: <a href="tel:' . htmlspecialchars($supportPhone) . '" class="support-link">' . htmlspecialchars($supportPhone) . '</a>
            </div>
        </div>
    </div>

    <script>
        $(if chap-id)
        function doLogin() {
            document.sendin.username.value = document.login.username.value;
            // For voucher authentication, password is typically the same as username
            var password = document.login.username.value;
            document.sendin.password.value = hexMD5(\'$(chap-id)\' + password + \'$(chap-challenge)\');
            document.sendin.submit();
            return false;
        }

        // Simplified MD5 function for CHAP authentication
        function hexMD5(str) {
            // Basic MD5 implementation for MikroTik CHAP
            var md5 = function(string) {
                function RotateLeft(lValue, iShiftBits) {
                    return (lValue<<iShiftBits) | (lValue>>>(32-iShiftBits));
                }
                function AddUnsigned(lX,lY) {
                    var lX4,lY4,lX8,lY8,lResult;
                    lX8 = (lX & 0x80000000);
                    lY8 = (lY & 0x80000000);
                    lX4 = (lX & 0x40000000);
                    lY4 = (lY & 0x40000000);
                    lResult = (lX & 0x3FFFFFFF)+(lY & 0x3FFFFFFF);
                    if (lX4 & lY4) {
                        return (lResult ^ 0x80000000 ^ lX8 ^ lY8);
                    }
                    if (lX4 | lY4) {
                        if (lResult & 0x40000000) {
                            return (lResult ^ 0xC0000000 ^ lX8 ^ lY8);
                        } else {
                            return (lResult ^ 0x40000000 ^ lX8 ^ lY8);
                        }
                    } else {
                        return (lResult ^ lX8 ^ lY8);
                    }
                }
                function F(x,y,z) { return (x & y) | ((~x) & z); }
                function G(x,y,z) { return (x & z) | (y & (~z)); }
                function H(x,y,z) { return (x ^ y ^ z); }
                function I(x,y,z) { return (y ^ (x | (~z))); }
                function FF(a,b,c,d,x,s,ac) {
                    a = AddUnsigned(a, AddUnsigned(AddUnsigned(F(b, c, d), x), ac));
                    return AddUnsigned(RotateLeft(a, s), b);
                };
                function GG(a,b,c,d,x,s,ac) {
                    a = AddUnsigned(a, AddUnsigned(AddUnsigned(G(b, c, d), x), ac));
                    return AddUnsigned(RotateLeft(a, s), b);
                };
                function HH(a,b,c,d,x,s,ac) {
                    a = AddUnsigned(a, AddUnsigned(AddUnsigned(H(b, c, d), x), ac));
                    return AddUnsigned(RotateLeft(a, s), b);
                };
                function II(a,b,c,d,x,s,ac) {
                    a = AddUnsigned(a, AddUnsigned(AddUnsigned(I(b, c, d), x), ac));
                    return AddUnsigned(RotateLeft(a, s), b);
                };
                function ConvertToWordArray(string) {
                    var lWordCount;
                    var lMessageLength = string.length;
                    var lNumberOfWords_temp1=lMessageLength + 8;
                    var lNumberOfWords_temp2=(lNumberOfWords_temp1-(lNumberOfWords_temp1 % 64))/64;
                    var lNumberOfWords = (lNumberOfWords_temp2+1)*16;
                    var lWordArray=Array(lNumberOfWords-1);
                    var lBytePosition = 0;
                    var lByteCount = 0;
                    while ( lByteCount < lMessageLength ) {
                        lWordCount = (lByteCount-(lByteCount % 4))/4;
                        lBytePosition = (lByteCount % 4)*8;
                        lWordArray[lWordCount] = (lWordArray[lWordCount] | (string.charCodeAt(lByteCount)<<lBytePosition));
                        lByteCount++;
                    }
                    lWordCount = (lByteCount-(lByteCount % 4))/4;
                    lBytePosition = (lByteCount % 4)*8;
                    lWordArray[lWordCount] = lWordArray[lWordCount] | (0x80<<lBytePosition);
                    lWordArray[lNumberOfWords-2] = lMessageLength<<3;
                    lWordArray[lNumberOfWords-1] = lMessageLength>>>29;
                    return lWordArray;
                };
                function WordToHex(lValue) {
                    var WordToHexValue="",WordToHexValue_temp="",lByte,lCount;
                    for (lCount = 0;lCount<=3;lCount++) {
                        lByte = (lValue>>>(lCount*8)) & 255;
                        WordToHexValue_temp = "0" + lByte.toString(16);
                        WordToHexValue = WordToHexValue + WordToHexValue_temp.substr(WordToHexValue_temp.length-2,2);
                    }
                    return WordToHexValue;
                };
                var x=Array();
                var k,AA,BB,CC,DD,a,b,c,d;
                var S11=7, S12=12, S13=17, S14=22;
                var S21=5, S22=9 , S23=14, S24=20;
                var S31=4, S32=11, S33=16, S34=23;
                var S41=6, S42=10, S43=15, S44=21;
                string = string.replace(/\r\n/g,"\n");
                x = ConvertToWordArray(string);
                a = 0x67452301; b = 0xEFCDAB89; c = 0x98BADCFE; d = 0x10325476;
                for (k=0;k<x.length;k+=16) {
                    AA=a; BB=b; CC=c; DD=d;
                    a=FF(a,b,c,d,x[k+0], S11,0xD76AA478);
                    d=FF(d,a,b,c,x[k+1], S12,0xE8C7B756);
                    c=FF(c,d,a,b,x[k+2], S13,0x242070DB);
                    b=FF(b,c,d,a,x[k+3], S14,0xC1BDCEEE);
                    a=FF(a,b,c,d,x[k+4], S11,0xF57C0FAF);
                    d=FF(d,a,b,c,x[k+5], S12,0x4787C62A);
                    c=FF(c,d,a,b,x[k+6], S13,0xA8304613);
                    b=FF(b,c,d,a,x[k+7], S14,0xFD469501);
                    a=FF(a,b,c,d,x[k+8], S11,0x698098D8);
                    d=FF(d,a,b,c,x[k+9], S12,0x8B44F7AF);
                    c=FF(c,d,a,b,x[k+10],S13,0xFFFF5BB1);
                    b=FF(b,c,d,a,x[k+11],S14,0x895CD7BE);
                    a=FF(a,b,c,d,x[k+12],S11,0x6B901122);
                    d=FF(d,a,b,c,x[k+13],S12,0xFD987193);
                    c=FF(c,d,a,b,x[k+14],S13,0xA679438E);
                    b=FF(b,c,d,a,x[k+15],S14,0x49B40821);
                    a=GG(a,b,c,d,x[k+1], S21,0xF61E2562);
                    d=GG(d,a,b,c,x[k+6], S22,0xC040B340);
                    c=GG(c,d,a,b,x[k+11],S23,0x265E5A51);
                    b=GG(b,c,d,a,x[k+0], S24,0xE9B6C7AA);
                    a=GG(a,b,c,d,x[k+5], S21,0xD62F105D);
                    d=GG(d,a,b,c,x[k+10],S22,0x2441453);
                    c=GG(c,d,a,b,x[k+15],S23,0xD8A1E681);
                    b=GG(b,c,d,a,x[k+4], S24,0xE7D3FBC8);
                    a=GG(a,b,c,d,x[k+9], S21,0x21E1CDE6);
                    d=GG(d,a,b,c,x[k+14],S22,0xC33707D6);
                    c=GG(c,d,a,b,x[k+3], S23,0xF4D50D87);
                    b=GG(b,c,d,a,x[k+8], S24,0x455A14ED);
                    a=GG(a,b,c,d,x[k+13],S21,0xA9E3E905);
                    d=GG(d,a,b,c,x[k+2], S22,0xFCEFA3F8);
                    c=GG(c,d,a,b,x[k+7], S23,0x676F02D9);
                    b=GG(b,c,d,a,x[k+12],S24,0x8D2A4C8A);
                    a=HH(a,b,c,d,x[k+5], S31,0xFFFA3942);
                    d=HH(d,a,b,c,x[k+8], S32,0x8771F681);
                    c=HH(c,d,a,b,x[k+11],S33,0x6D9D6122);
                    b=HH(b,c,d,a,x[k+14],S34,0xFDE5380C);
                    a=HH(a,b,c,d,x[k+1], S31,0xA4BEEA44);
                    d=HH(d,a,b,c,x[k+4], S32,0x4BDECFA9);
                    c=HH(c,d,a,b,x[k+7], S33,0xF6BB4B60);
                    b=HH(b,c,d,a,x[k+10],S34,0xBEBFBC70);
                    a=HH(a,b,c,d,x[k+13],S31,0x289B7EC6);
                    d=HH(d,a,b,c,x[k+0], S32,0xEAA127FA);
                    c=HH(c,d,a,b,x[k+3], S33,0xD4EF3085);
                    b=HH(b,c,d,a,x[k+6], S34,0x4881D05);
                    a=HH(a,b,c,d,x[k+9], S31,0xD9D4D039);
                    d=HH(d,a,b,c,x[k+12],S32,0xE6DB99E5);
                    c=HH(c,d,a,b,x[k+15],S33,0x1FA27CF8);
                    b=HH(b,c,d,a,x[k+2], S34,0xC4AC5665);
                    a=II(a,b,c,d,x[k+0], S41,0xF4292244);
                    d=II(d,a,b,c,x[k+7], S42,0x432AFF97);
                    c=II(c,d,a,b,x[k+14],S43,0xAB9423A7);
                    b=II(b,c,d,a,x[k+5], S44,0xFC93A039);
                    a=II(a,b,c,d,x[k+12],S41,0x655B59C3);
                    d=II(d,a,b,c,x[k+3], S42,0x8F0CCC92);
                    c=II(c,d,a,b,x[k+10],S43,0xFFEFF47D);
                    b=II(b,c,d,a,x[k+1], S44,0x85845DD1);
                    a=II(a,b,c,d,x[k+8], S41,0x6FA87E4F);
                    d=II(d,a,b,c,x[k+15],S42,0xFE2CE6E0);
                    c=II(c,d,a,b,x[k+6], S43,0xA3014314);
                    b=II(b,c,d,a,x[k+13],S44,0x4E0811A1);
                    a=II(a,b,c,d,x[k+4], S41,0xF7537E82);
                    d=II(d,a,b,c,x[k+11],S42,0xBD3AF235);
                    c=II(c,d,a,b,x[k+2], S43,0x2AD7D2BB);
                    b=II(b,c,d,a,x[k+9], S44,0xEB86D391);
                    a=AddUnsigned(a,AA);
                    b=AddUnsigned(b,BB);
                    c=AddUnsigned(c,CC);
                    d=AddUnsigned(d,DD);
                }
                var temp = WordToHex(a)+WordToHex(b)+WordToHex(c)+WordToHex(d);
                return temp.toLowerCase();
            }
            return md5(str);
        }
        $(endif)

        // Focus on voucher input when page loads
        window.onload = function() {
            document.getElementById("username").focus();
        };
    </script>
</body>
</html>';
?>
