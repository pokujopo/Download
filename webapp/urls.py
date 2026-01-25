from django.urls import path
from . import views

urlpatterns = [
      path('', views.netvideo, name='netvideo'),
      path('download_video/', views.download_video, name='download_video'),
      path('loginpage/', views.loginpage, name='login page'),
      path('registerpage/', views.registerpage, name='registerpage'),
      #path('register/', views.register, name='register'),
      path('user/', views.user, name="user"),
 
   ]
