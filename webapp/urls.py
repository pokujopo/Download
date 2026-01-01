from django.urls import path
from . import views

urlpatterns = [
      path('', views.netvideo, name='netvideo'),
      path('download_video/', views.download_video, name='download_video'),
 
   ]
