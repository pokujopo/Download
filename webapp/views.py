from django.shortcuts import render, redirect
from django.views.decorators.csrf import csrf_exempt
import json
import yt_dlp
from django.http import JsonResponse
from .models import User_info

def netvideo(request):
	return render(request, 'webapp/netvideo.html' )

@csrf_exempt
def download_video(request):
    link = request.POST.get("link")
    ydl_opts = {
    'cookiefile': '/storage/emulated/0/Youtube video/cookies.txt',
    'format': 'best'}
    with yt_dlp.YoutubeDL(ydl_opts) as ydl:
        ydl.download(link)
    
    return JsonResponse({"status":"ook","link":link})


#login na register controller Zina anzia apa

def registerpage(request):
      if request.method == "POST":
        username = request.POST.get("username")
        email = request.POST.get("email")
        password = request.POST.get("password")
        confirm_pass = request.POST.get("confirm_password")
        
        if confirm_pass == password:
           User_info.objects.create(username=username, email=email, password=password)
           return redirect("/loginpage")
        
        
        #return JsonResponse({"status": "data zimepokelewa..", "username": username})                         
    
    
      return render(request, 'webapp/register.html')
 
def user(request):
    return render(request, 'webapp/user.html')
    
def loginpage(request):
    if request.method =='POST':
        email = request.POST.get('email')
        password=request.POST.get('password')
        
        checkIfUserExist = User_info.objects.get(email=email, password=password)
        if checkIfUserExist:
            return redirect('/user')
        else:
            return redirect('/registerpage ')
    return render(request, 'webapp/loginpage.html')                      
    