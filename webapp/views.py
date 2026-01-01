from django.shortcuts import render, redirect
from django.views.decorators.csrf import csrf_exempt
import json
import yt_dlp
from django.http import JsonResponse

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

