# Facebook Page Auto Poster for WordPress  
ปลั๊กอิน WordPress สำหรับ **ดึงโพสต์จากเพจ Facebook** มาเป็นโพสต์บนเว็บไซต์อัตโนมัติ  
พร้อมระบบ **Auto Refresh Token** และ **Placeholder Image**  

---

## ✨ Features
- 🔄 ดึงโพสต์จาก Facebook Page อัตโนมัติ (ค่าเริ่มต้นทุก 1 ชั่วโมง)  
- 🚀 ปุ่ม **Fetch Now** ดึงโพสต์ทันทีจากหน้า Settings  
- 🖼 ดึงรูปจาก Facebook มาเป็น Featured Image  
- 📌 ถ้าโพสต์ไม่มีรูป → ใส่ **Placeholder Image** (ข้อความเช่น “คณะครุศาสตร์”)  
- 🔑 ต่ออายุ Access Token อัตโนมัติ (ใช้ App ID / Secret + Long-lived User Token)  
- 🚫 ป้องกันโพสต์ซ้ำด้วย `fb_post_id`  
- 📝 มีหน้า Settings ใน WP Admin ใช้งานง่าย  

---

## 📦 Installation
1. ดาวน์โหลด repo นี้ แล้ววางโฟลเดอร์ไว้ที่:  wp-content/plugins/fb-to-wp/
2. หรือ zip ไฟล์เป็น `fb-to-wp.zip` แล้วอัปโหลดผ่าน WordPress → Plugins → Add New → Upload Plugin  
3. ไปที่ WP Admin → Plugins → Activate  

---

## ⚙️ Setup Facebook App (ขอ App ID, Secret, Token)
1. ไปที่ [Facebook for Developers](https://developers.facebook.com/) → **My Apps** → **Create App**  
- เลือก **For Everything Else**  
- ตั้งชื่อแอป เช่น `WP Auto Poster`  
- สร้างเสร็จ → จะได้ **App ID** และ **App Secret** (อยู่ที่ **Settings → Basic**)  

2. ไปที่ [Graph API Explorer](https://developers.facebook.com/tools/explorer/)  
- เลือกแอปที่คุณสร้าง  
- ติ๊กสิทธิ์อย่างน้อย:  
  - `pages_show_list`  
  - `pages_read_engagement`  
  - (แนะนำ) `pages_manage_posts`  
- กด **Generate Access Token** → จะได้ **Short-lived User Token**  

3. แปลงเป็น **Long-lived User Token (~60 วัน)**  
- ไปที่ [Access Token Debugger](https://developers.facebook.com/tools/debug/)  
- วาง token → กด **Extend Access Token**  
- จะได้ Long-lived User Token (อายุประมาณ 60 วัน)  

4. ใช้ Long-lived User Token ดึง **Page Access Token**  
```bash
curl -G "https://graph.facebook.com/v20.0/me/accounts" \
  --data-urlencode "fields=id,name,access_token" \
  --data-urlencode "access_token=LONG_LIVED_USER_TOKEN"

ผลลัพธ์:
{
  "data": [
    {
      "id": "123456789012345",
      "name": "PageName",
      "access_token": "EAAG....your-page-token"
    }
  ]
}
