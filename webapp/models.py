from django.db import models

# Create your models here.

class User_info(models.Model):
    username = models.CharField(max_length=20)
    email = models.EmailField(unique=True)
    password = models.CharField(max_length=30)
    time = models.DateField(auto_now_add=True)