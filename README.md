# Facebook Page Auto Poster for WordPress  
ดึงโพสต์จาก **Facebook Page** มาลงเป็นโพสต์ใน **WordPress** อัตโนมัติ พร้อมระบบ **Auto Refresh Token** และ **Placeholder Image**

## ✨ Features
- 🔄 ดึงโพสต์จาก Facebook Page อัตโนมัติ (ทุก ๆ 1 ชั่วโมง)   
- 🖼 ดึงรูปจาก Facebook มาเป็น Featured Image  
- 📌 ถ้าโพสต์ไม่มีรูป → ใส่ **Placeholder Image**  
- 🔑 ต่ออายุ Access Token อัตโนมัติ (ใช้ App ID / App Secret + Long-lived User Token)  
- 🚫 ป้องกันโพสต์ซ้ำด้วย `fb_post_id` 

---

## 📦 Installation
1. ดาวน์โหลดหรือโคลน repo นี้  
วางโฟลเดอร์ไว้ที่: wp-content/plugins/fb-to-wp/
เข้า WordPress Admin → Plugins → Activate

⚙️ Configuration
ไปที่เมนู FB Auto Poster ใน WordPress แล้วกรอก: 
	1.App ID / App Secret (จาก Facebook App)
	2.Long-lived User Token (~60 วัน)
	3.Page ID (เช่น 2385279...)

จากนั้นกด ♻️ Refresh Tokens Now → ปลั๊กอินจะดึง Page Access Token ให้อัตโนมัติ

🖼 Placeholder Options
Generate mode: สร้างภาพพื้นหลัง + ข้อความ (กำหนดสี/ฟอนต์/ขนาดเอง)

Upload mode: ใช้รูปจาก Media Library (ใส่ Attachment ID)

ถ้าโพสต์ Facebook ไม่มีรูป → ปลั๊กอินจะใช้ Placeholder แทน

🔒 Security
Token ถูกเก็บไว้ใน WordPress options (เฉพาะ Admin เท่านั้นที่แก้ไขได้)

Debug log จะ mask token ป้องกันการรั่วไหล

ใช้สิทธิ์ Facebook เฉพาะที่จำเป็น (อ่านโพสต์เพจ)

🕒 Cron jobs
ดึงโพสต์อัตโนมัติทุก 1 ชั่วโมง (แก้โค้ดได้ถ้าต้องการความถี่อื่น)

ตรวจสอบอายุโทเคนทุกวัน → ถ้าใกล้หมดอายุ (≤15 วัน) จะรีเฟรชให้อัตโนมัติ

📜 License
GPL v2 or later.
คุณสามารถนำไปใช้งาน แก้ไข และเผยแพร่ต่อได้ตามสัญญาอนุญาตของ WordPress plugins.

🙌 Credits
พัฒนาโดย CloundP
