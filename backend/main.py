from fastapi import FastAPI, HTTPException, Depends, Request, Form, File, UploadFile
from fastapi.security import HTTPBearer, HTTPAuthorizationCredentials
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse, FileResponse, HTMLResponse
from fastapi.staticfiles import StaticFiles
from sqlalchemy import create_engine, Column, String, DateTime, text
from sqlalchemy.ext.declarative import declarative_base
from sqlalchemy.orm import sessionmaker, Session
from passlib.context import CryptContext
from datetime import datetime, timedelta
from typing import Optional, List
import jwt
import os
import smtplib
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
import json
from pathlib import Path
import logging
from dotenv import load_dotenv
from pydantic import BaseModel, EmailStr

# Load environment variables
load_dotenv()

# Database setup
DATABASE_URL = f"mysql+pymysql://{os.getenv('DB_USER')}:{os.getenv('DB_PASS')}@{os.getenv('DB_HOST')}/{os.getenv('DB_NAME')}"
engine = create_engine(DATABASE_URL)
SessionLocal = sessionmaker(autocommit=False, autoflush=False, bind=engine)
Base = declarative_base()

# Password hashing
pwd_context = CryptContext(schemes=["bcrypt"], deprecated="auto")

# JWT settings
SECRET_KEY = os.getenv("JWT_SECRET_KEY", "your-secret-key-change-this")
ALGORITHM = "HS256"
ACCESS_TOKEN_EXPIRE_MINUTES = 120  # 2 hours like PHP timeout

app = FastAPI(title="Log Viewer API", version="1.0.0")

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Serve static files
app.mount("/static", StaticFiles(directory="static"), name="static")

# Database Models
class User(Base):
    __tablename__ = "users"
    
    email = Column(String(255), primary_key=True, index=True)
    password = Column(String(255), nullable=False)
    role = Column(String(50), nullable=False, default="user")
    status = Column(String(50), nullable=False, default="pending")
    created_at = Column(DateTime, default=datetime.utcnow)

# Create tables
Base.metadata.create_all(bind=engine)

# Pydantic models
class UserSignup(BaseModel):
    email: EmailStr
    password: str
    confirm: str

class UserLogin(BaseModel):
    email: EmailStr
    password: str

class UserUpdate(BaseModel):
    email: EmailStr
    role: str
    status: str

class UserStatusUpdate(BaseModel):
    email: EmailStr
    status: str

class Token(BaseModel):
    access_token: str
    token_type: str
    role: str
    email: str

# Security
security = HTTPBearer()

# Dependency to get database session
def get_db():
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()

# JWT token functions
def create_access_token(data: dict, expires_delta: Optional[timedelta] = None):
    to_encode = data.copy()
    if expires_delta:
        expire = datetime.utcnow() + expires_delta
    else:
        expire = datetime.utcnow() + timedelta(minutes=15)
    to_encode.update({"exp": expire})
    encoded_jwt = jwt.encode(to_encode, SECRET_KEY, algorithm=ALGORITHM)
    return encoded_jwt

def verify_token(credentials: HTTPAuthorizationCredentials = Depends(security)):
    try:
        payload = jwt.decode(credentials.credentials, SECRET_KEY, algorithms=[ALGORITHM])
        email: str = payload.get("sub")
        if email is None:
            raise HTTPException(status_code=401, detail="Invalid token")
        return payload
    except jwt.ExpiredSignatureError:
        raise HTTPException(status_code=401, detail="Token expired")
    except jwt.JWTError:
        raise HTTPException(status_code=401, detail="Invalid token")

# Email functions
def send_admin_notification(user_email: str):
    try:
        msg = MIMEMultipart()
        msg['From'] = os.getenv('MAIL_FROM')
        msg['To'] = "okezie.austin@dufil.com,okezie.austin@tolaram.com"
        msg['Subject'] = "New User Signup Request"
        
        body = f"""
        <h2>New Sign-Up Request</h2>
        <p>Email: <strong>{user_email}</strong></p>
        <p><a href='http://localhost:8000/admin'>Go to Admin Panel to approve or reject</a></p>
        """
        
        msg.attach(MIMEText(body, 'html'))
        
        server = smtplib.SMTP(os.getenv('MAIL_HOST'), int(os.getenv('MAIL_PORT')))
        server.starttls()
        server.login(os.getenv('MAIL_USER'), os.getenv('MAIL_PASS'))
        text = msg.as_string()
        server.sendmail(os.getenv('MAIL_FROM'), ["okezie.austin@dufil.com", "okezie.austin@tolaram.com"], text)
        server.quit()
        return True
    except Exception as e:
        logging.error(f"Email error: {e}")
        return False

# Utility functions
def verify_password(plain_password, hashed_password):
    return pwd_context.verify(plain_password, hashed_password)

def get_password_hash(password):
    return pwd_context.hash(password)

def get_log_folders():
    base_dir = Path("C:/wamp64/www")  # Adjust path as needed
    folders_with_logs = []
    
    if not base_dir.exists():
        return folders_with_logs
    
    for folder_path in base_dir.iterdir():
        if folder_path.is_dir() and folder_contains_log_file(folder_path):
            folders_with_logs.append(folder_path.name)
    
    return folders_with_logs

def folder_contains_log_file(folder_path: Path):
    for file_path in folder_path.rglob("*.log"):
        if file_path.is_file():
            return True
    return False

def get_log_files_in_folder(folder: str):
    folder_path = Path(f"C:/wamp64/www/{folder}")  # Adjust path as needed
    log_files = []
    
    if not folder_path.exists():
        return log_files
    
    for file_path in folder_path.rglob("*.log"):
        if file_path.is_file():
            relative_path = file_path.relative_to(folder_path)
            log_files.append(str(relative_path))
    
    return log_files

# API Routes

@app.post("/api/signup", response_model=dict)
async def signup_user(user: UserSignup, db: Session = Depends(get_db)):
    if len(user.password) < 6:
        raise HTTPException(status_code=400, detail="Password must be at least 6 characters")
    
    if user.password != user.confirm:
        raise HTTPException(status_code=400, detail="Passwords do not match")
    
    # Check if email already exists
    existing_user = db.query(User).filter(User.email == user.email).first()
    if existing_user:
        raise HTTPException(status_code=400, detail="Email already registered")
    
    # Hash password and create user
    hashed_password = get_password_hash(user.password)
    db_user = User(email=user.email, password=hashed_password)
    db.add(db_user)
    db.commit()
    
    # Send admin notification
    email_sent = send_admin_notification(user.email)
    
    if email_sent:
        return {"status": "success", "message": "Registration successful. Await admin approval."}
    else:
        return {"status": "warning", "message": "User saved, but email failed to send"}

@app.post("/api/login", response_model=Token)
async def login_user(user: UserLogin, db: Session = Depends(get_db)):
    db_user = db.query(User).filter(User.email == user.email).first()
    
    if not db_user or not verify_password(user.password, db_user.password):
        raise HTTPException(status_code=401, detail="Invalid email or password")
    
    if db_user.status == "pending":
        raise HTTPException(status_code=403, detail="Your account is pending approval")
    
    if db_user.status == "disabled":
        raise HTTPException(status_code=403, detail="Your account has been disabled")
    
    # Create access token
    access_token_expires = timedelta(minutes=ACCESS_TOKEN_EXPIRE_MINUTES)
    access_token = create_access_token(
        data={"sub": db_user.email, "role": db_user.role}, 
        expires_delta=access_token_expires
    )
    
    return {
        "access_token": access_token,
        "token_type": "bearer",
        "role": db_user.role,
        "email": db_user.email
    }

@app.get("/api/log-folders")
async def get_log_folders_endpoint(current_user: dict = Depends(verify_token)):
    return get_log_folders()

@app.post("/api/log-files")
async def get_log_files_endpoint(
    folder: str = Form(...),
    current_user: dict = Depends(verify_token)
):
    return get_log_files_in_folder(folder)

@app.post("/api/read-log")
async def read_log_file(
    folder: str = Form(...),
    file: str = Form(...),
    current_user: dict = Depends(verify_token)
):
    base_path = Path(f"C:/wamp64/www/{folder}")  # Adjust path as needed
    file_path = base_path / file
    
    if not file_path.exists():
        raise HTTPException(status_code=404, detail="File not found")
    
    if not file_path.is_file():
        raise HTTPException(status_code=400, detail="Path is not a file")
    
    try:
        with open(file_path, 'r', encoding='utf-8') as f:
            content = f.read()
        return {"content": content}
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Error reading file: {str(e)}")

# Admin routes
@app.get("/api/users")
async def get_users(current_user: dict = Depends(verify_token), db: Session = Depends(get_db)):
    if current_user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Admin access required")
    
    users = db.query(User).filter(~User.status.in_(["pending", "declined"])).all()
    return [{"email": user.email, "role": user.role, "status": user.status} for user in users]

@app.get("/api/pending-users")
async def get_pending_users(current_user: dict = Depends(verify_token), db: Session = Depends(get_db)):
    if current_user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Admin access required")
    
    users = db.query(User).filter(User.status == "pending").all()
    return [{"email": user.email} for user in users]

@app.get("/api/user/{email}")
async def get_user(email: str, current_user: dict = Depends(verify_token), db: Session = Depends(get_db)):
    if current_user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Admin access required")
    
    user = db.query(User).filter(User.email == email).first()
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    
    return {"email": user.email, "role": user.role, "status": user.status}

@app.put("/api/user")
async def update_user(
    user_update: UserUpdate,
    current_user: dict = Depends(verify_token),
    db: Session = Depends(get_db)
):
    if current_user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Admin access required")
    
    user = db.query(User).filter(User.email == user_update.email).first()
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    
    user.role = user_update.role
    user.status = user_update.status
    db.commit()
    
    return {"status": "ok"}

@app.put("/api/user-status")
async def update_user_status(
    status_update: UserStatusUpdate,
    current_user: dict = Depends(verify_token),
    db: Session = Depends(get_db)
):
    if current_user.get("role") != "admin":
        raise HTTPException(status_code=403, detail="Admin access required")
    
    user = db.query(User).filter(User.email == status_update.email).first()
    if not user:
        raise HTTPException(status_code=404, detail="User not found")
    
    user.status = status_update.status
    db.commit()
    
    return {"status": "ok"}

# Serve HTML pages
@app.get("/", response_class=HTMLResponse)
async def serve_index():
    with open("templates/index.html", "r") as f:
        return HTMLResponse(content=f.read())

@app.get("/home", response_class=HTMLResponse)
async def serve_home():
    with open("templates/home.html", "r") as f:
        return HTMLResponse(content=f.read())

@app.get("/admin", response_class=HTMLResponse)
async def serve_admin():
    with open("templates/admin.html", "r") as f:
        return HTMLResponse(content=f.read())

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=8000)